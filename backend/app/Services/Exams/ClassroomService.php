<?php

namespace App\Services\Exams;

use App\Models\Classroom;
use App\Services\Security\AuditLogService;
use App\Services\Security\CurrentUserService;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registra y lista ambientes del catálogo institucional (HU-07).
 */
class ClassroomService
{
    private AuditLogService $auditLog;

    private CurrentUserService $currentUser;

    public function __construct(AuditLogService $auditLog, CurrentUserService $currentUser)
    {
        $this->auditLog = $auditLog;
        $this->currentUser = $currentUser;
    }

    /** Catálogo completo, del más reciente uso al listado alfabético (HU-24 lo consume igual). */
    public function listAll(): Collection
    {
        return Classroom::query()
            ->orderBy('nro_aula')
            ->get();
    }

    /**
     * Crea el ambiente con estado ACTIVO dentro de una transacción, y deja
     * constancia en bitácora.
     */
    public function registrar(array $data): Classroom
    {
        return DB::transaction(function () use ($data) {
            $classroom = Classroom::create([
                'nro_aula' => $data['nro_aula'],
                'capacidad' => $data['capacidad'],
                'ubicacion' => $data['ubicacion'],
                'estado' => RecordStatus::ACTIVE,
            ]);

            $this->auditLog->registrar(
                'CREAR',
                'ambiente',
                null,
                $classroom->only(['id_ambiente', 'nro_aula', 'capacidad', 'ubicacion', 'estado']),
                $this->currentUser->id()
            );

            return $classroom;
        });
    }
}