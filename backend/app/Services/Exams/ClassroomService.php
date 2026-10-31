<?php

namespace App\Services\Exams;

use App\Models\Classroom;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra y lista ambientes del catálogo institucional (HU-07).
 */
class ClassroomService
{
    private AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
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
    public function registrar(array $data, string $actorId): Classroom
    {
        return DB::transaction(function () use ($data, $actorId) {
            try {
                $classroom = Classroom::create([
                    'nro_aula' => $data['nro_aula'],
                    'capacidad' => $data['capacidad'],
                    'ubicacion' => $data['ubicacion'],
                    'estado' => RecordStatus::ACTIVE,
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueViolation($exception)) {
                    throw $this->duplicateClassroomException();
                }

                if ($this->isCheckViolation($exception)) {
                    throw $this->invalidCapacityException();
                }

                throw $exception;
            }

            $this->auditLog->registrar(
                'CREAR',
                'ambiente',
                null,
                $classroom->only(['id_ambiente', 'nro_aula', 'capacidad', 'ubicacion', 'estado']),
                $actorId
            );

            return $classroom;
        });
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        // 23505 es el SQLSTATE de "unique_violation" en PostgreSQL.
        return $exception->getCode() === '23505';
    }

    private function isCheckViolation(QueryException $exception): bool
    {
        // 23514 es el SQLSTATE de "check_violation" en PostgreSQL.
        return $exception->getCode() === '23514';
    }

    private function duplicateClassroomException(): ValidationException
    {
        return ValidationException::withMessages([
            'nro_aula' => ['Ya existe un ambiente registrado con ese nombre.'],
        ]);
    }

    private function invalidCapacityException(): ValidationException
    {
        return ValidationException::withMessages([
            'capacidad' => ['La capacidad debe ser mayor a cero.'],
        ]);
    }
}