<?php

namespace App\Services\Academic;

use App\Models\Career;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectCareerAssignmentService
{
    private AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    public function listActiveCareers(): Collection
    {
        return Career::query()
            ->where('estado', RecordStatus::ACTIVE)
            ->orderBy('nombre')
            ->get();
    }

    public function listAssignableSubjects(Career $career): Collection
    {
        $this->assertCareerIsActive($career);

        return Subject::query()
            ->activas()
            ->whereDoesntHave('subjectCareers', function ($query) use ($career) {
                $query->where('id_carrera', $career->id_carrera);
            })
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Pares materia-carrera de cualquier estado, ordenados por carrera y materia. La carrera y la
     * materia viajan con un join y se cargan en dos consultas fijas, sin importar cuántos pares haya.
     */
    public function listAssignments(?int $careerId = null): Collection
    {
        return SubjectCareer::query()
            ->join('carrera', 'carrera.id_carrera', '=', 'materia_carrera.id_carrera')
            ->join('materia', 'materia.id_materia', '=', 'materia_carrera.id_materia')
            ->select('materia_carrera.*')
            ->when(
                $careerId !== null,
                fn ($query) => $query->where('materia_carrera.id_carrera', $careerId)
            )
            ->with(['career', 'subject'])
            ->orderBy('carrera.nombre')
            ->orderBy('materia.nombre')
            ->orderBy('materia_carrera.id_materia')
            ->get();
    }

    /**
     * Carreras con al menos un par, activas o no, por nombre. No depende del filtro de la lista:
     * alimenta el selector de carreras de la pestaña Asignaciones.
     */
    public function listAssignedCareers(): Collection
    {
        return Career::query()
            ->whereIn('id_carrera', SubjectCareer::query()->select('id_carrera'))
            ->orderBy('nombre')
            ->orderBy('id_carrera')
            ->get();
    }

    public function assign(Career $career, int $subjectId, string $actorId): SubjectCareer
    {
        return DB::transaction(function () use ($career, $subjectId, $actorId) {
            $lockedCareer = Career::query()
                ->lockForUpdate()
                ->findOrFail($career->id_carrera);

            $subject = Subject::query()
                ->lockForUpdate()
                ->findOrFail($subjectId);

            $this->assertCareerIsActive($lockedCareer);
            $this->assertSubjectIsActive($subject);

            $this->assertPairDoesNotExist(
                (int) $lockedCareer->id_carrera,
                (int) $subject->id_materia
            );

            try {
                $pair = SubjectCareer::create([
                    'id_carrera' => $lockedCareer->id_carrera,
                    'id_materia' => $subject->id_materia,
                    'estado' => RecordStatus::ACTIVE,
                ]);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) === '23505') {
                    throw ValidationException::withMessages([
                        'id_materia' => [
                            'La materia ya está asignada a la carrera seleccionada.',
                        ],
                    ]);
                }

                throw $exception;
            }

            $this->auditLog->registrar(
                'CREAR',
                'materia_carrera',
                null,
                [
                    'id_carrera' => (int) $pair->id_carrera,
                    'id_materia' => (int) $pair->id_materia,
                    'estado' => $pair->estado,
                ],
                $actorId
            );

            return $pair->load(['career', 'subject']);
        });
    }

    private function assertCareerIsActive(Career $career): void
    {
        if ($career->estado === RecordStatus::ACTIVE) {
            return;
        }

        throw ValidationException::withMessages([
            'id_carrera' => [
                'La carrera seleccionada no está activa.',
            ],
        ]);
    }

    private function assertSubjectIsActive(Subject $subject): void
    {
        if ($subject->estado === RecordStatus::ACTIVE) {
            return;
        }

        throw ValidationException::withMessages([
            'id_materia' => [
                'La materia seleccionada no está activa.',
            ],
        ]);
    }

    private function assertPairDoesNotExist(int $careerId, int $subjectId): void
    {
        $exists = SubjectCareer::query()
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->exists();

        if (! $exists) {
            return;
        }

        throw ValidationException::withMessages([
            'id_materia' => [
                'La materia ya está asignada a la carrera seleccionada.',
            ],
        ]);
    }
}