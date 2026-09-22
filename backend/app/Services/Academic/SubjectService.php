<?php

namespace App\Services\Academic;

use App\Models\Subject;
use App\Services\Security\AuditLogService;
use Illuminate\Support\Facades\DB;

class SubjectService
{
    private AuditLogService $auditService;

    public function __construct(AuditLogService $auditService)
    {
        $this->auditService = $auditService;
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
}