<?php

namespace App\Services\Academic;

use App\Models\Subject;
use App\Services\Security\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class SubjectService
{
    private AuditLogService $auditService;

    public function __construct(AuditLogService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function listForAdministration(): Collection
    {
        return Subject::query()
            ->orderBy('nombre')
            ->get();
    }

    public function create(array $data): Subject
    {
        return DB::transaction(function () use ($data) {
            $subject = Subject::create([
                'nombre'      => $data['nombre'],
                'codigo'      => $data['codigo'],
                'descripcion' => $data['descripcion'] ?? null,
                'estado'      => Subject::ESTADO_ACTIVO,
            ]);

            $this->auditService->registrar(
                'CREAR',
                'materia',
                null,
                $subject->toArray(),
                auth()->id()
            );

            return $subject;
        });
    }

    public function update(Subject $subject, array $data): Subject
    {
        return DB::transaction(function () use ($subject, $data) {
            $before = [];
            $after = [];

            foreach (['nombre', 'codigo'] as $field) {
                if ((string) $subject->{$field} !== (string) $data[$field]) {
                    $before[$field] = $subject->{$field};
                    $after[$field] = $data[$field];
                }
            }

            if ($after === []) {
                return $subject;
            }

            $subject->fill($after);
            $subject->save();

            $this->auditService->registrar(
                'MODIFICAR',
                'materia',
                $before,
                $after,
                auth()->id()
            );

            return $subject->fresh();
        });
    }
}
