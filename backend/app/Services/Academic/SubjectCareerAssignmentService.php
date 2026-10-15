<?php

namespace App\Services\Academic;

use App\Models\AcademicOffering;
use App\Models\Career;
use App\Models\Period;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-006: ofrecer una materia existente a una carrera en un período (gestion_periodo).
 *
 * La asignación es por período: crea el par permanente materia_carrera si todavía no existe y la
 * fila de oferta_academica del período elegido. La misma materia puede ofrecerse de nuevo en otro
 * período, pero no dos veces en el mismo (la clave de la oferta lo impide, incluso si la oferta
 * existente está INACTIVA: reactivarla pertenece a HU-008).
 *
 * Solo se escribe en el período vigente; los demás períodos son historial de consulta, para que la
 * bitácora siempre coincida con la oferta académica que realmente se ofreció.
 */
class SubjectCareerAssignmentService
{
    private AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /**
     * El período con su etiqueta, que debe existir y estar ACTIVO. La petición ya lo valida; esto
     * protege a quien llame al servicio sin pasar por ella.
     */
    public function activePeriod(int $periodId): Period
    {
        $period = Period::query()
            ->withLabel()
            ->where('gestion_periodo.id_gestion_periodo', $periodId)
            ->first();

        $this->assertPeriodIsActive($period);

        return $period;
    }

    public function listActiveCareers(): Collection
    {
        return Career::query()
            ->where('estado', RecordStatus::ACTIVE)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Materias activas que la carrera puede recibir en el período: sin oferta (de cualquier estado)
     * ahí y sin un par materia-carrera INACTIVO, que asignar rechazaría.
     */
    public function listAssignableSubjects(Career $career, Period $period): Collection
    {
        $this->assertCareerIsActive($career);
        $this->assertPeriodIsCurrent($period);

        return Subject::query()
            ->activas()
            ->whereNotExists(function ($query) use ($career, $period) {
                $query->selectRaw('1')
                    ->from('oferta_academica')
                    ->whereColumn('oferta_academica.id_materia', 'materia.id_materia')
                    ->where('oferta_academica.id_carrera', $career->id_carrera)
                    ->where('oferta_academica.id_gestion_periodo', $period->id_gestion_periodo);
            })
            ->whereNotExists(function ($query) use ($career) {
                $query->selectRaw('1')
                    ->from('materia_carrera')
                    ->whereColumn('materia_carrera.id_materia', 'materia.id_materia')
                    ->where('materia_carrera.id_carrera', $career->id_carrera)
                    ->where('materia_carrera.estado', '!=', RecordStatus::ACTIVE);
            })
            ->orderBy('nombre')
            ->orderBy('id_materia')
            ->get();
    }

    /**
     * Ofertas del período, de cualquier estado, ordenadas por carrera y materia. La carrera y la
     * materia viajan con un join y se cargan en dos consultas fijas; el período, ya resuelto, se
     * comparte entre todas las filas.
     */
    public function listAssignments(Period $period, ?int $careerId = null): Collection
    {
        $offerings = AcademicOffering::query()
            ->join('carrera', 'carrera.id_carrera', '=', 'oferta_academica.id_carrera')
            ->join('materia', 'materia.id_materia', '=', 'oferta_academica.id_materia')
            ->select('oferta_academica.*')
            ->where('oferta_academica.id_gestion_periodo', $period->id_gestion_periodo)
            ->when(
                $careerId !== null,
                fn ($query) => $query->where('oferta_academica.id_carrera', $careerId)
            )
            ->with(['career', 'subject'])
            ->orderBy('carrera.nombre')
            ->orderBy('materia.nombre')
            ->orderBy('oferta_academica.id_materia')
            ->get();

        $offerings->each(fn (AcademicOffering $offering) => $offering->setRelation('period', $period));

        return $offerings;
    }

    /**
     * Carreras del selector de filtro: todas las ACTIVAS más las INACTIVAS que tienen ofertas en el
     * período (para no esconder filas que la lista sí muestra). No depende del filtro de la lista.
     */
    public function listCareersForPeriod(Period $period): Collection
    {
        return Career::query()
            ->where(function ($query) use ($period) {
                $query->where('estado', RecordStatus::ACTIVE)
                    ->orWhereIn(
                        'id_carrera',
                        AcademicOffering::query()
                            ->select('id_carrera')
                            ->where('id_gestion_periodo', $period->id_gestion_periodo)
                    );
            })
            ->orderBy('nombre')
            ->orderBy('id_carrera')
            ->get();
    }

    public function assign(Career $career, int $subjectId, int $periodId, string $actorId): AcademicOffering
    {
        return DB::transaction(function () use ($career, $subjectId, $periodId, $actorId) {
            // El bloqueo de la carrera serializa las asignaciones concurrentes de la misma carrera.
            $lockedCareer = Career::query()
                ->lockForUpdate()
                ->findOrFail($career->id_carrera);

            $subject = Subject::query()
                ->lockForUpdate()
                ->findOrFail($subjectId);

            // FOR SHARE impide que `vigente` cambie hasta confirmar y lo lee ya confirmado.
            $period = Period::query()
                ->sharedLock()
                ->find($periodId);

            $this->assertCareerIsActive($lockedCareer);
            $this->assertSubjectIsActive($subject);
            $this->assertPeriodIsActive($period);
            $this->assertPeriodIsCurrent($period);

            $careerId = (int) $lockedCareer->id_carrera;
            $pair = SubjectCareer::query()
                ->where('id_carrera', $careerId)
                ->where('id_materia', $subject->id_materia)
                ->first();

            if ($pair !== null && $pair->estado !== RecordStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'id_materia' => ['El vínculo materia-carrera está inactivo.'],
                ]);
            }

            $this->assertOfferingDoesNotExist(
                $careerId,
                (int) $subject->id_materia,
                (int) $period->id_gestion_periodo
            );

            $pairCreated = $pair === null;

            if ($pairCreated) {
                $pair = SubjectCareer::create([
                    'id_carrera' => $careerId,
                    'id_materia' => $subject->id_materia,
                    'estado' => RecordStatus::ACTIVE,
                ]);
            }

            try {
                $offering = AcademicOffering::create([
                    'id_carrera' => $careerId,
                    'id_materia' => $subject->id_materia,
                    'id_gestion_periodo' => $period->id_gestion_periodo,
                    'estado' => RecordStatus::ACTIVE,
                ]);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) === '23505') {
                    throw $this->duplicateOffering();
                }

                throw $exception;
            }

            if ($pairCreated) {
                $this->auditLog->registrar(
                    'CREAR',
                    'materia_carrera',
                    null,
                    [
                        'id_carrera' => $careerId,
                        'id_materia' => (int) $subject->id_materia,
                        'estado' => $pair->estado,
                    ],
                    $actorId
                );
            }

            $this->auditLog->registrar(
                'CREAR',
                'oferta_academica',
                null,
                [
                    'id_carrera' => $careerId,
                    'id_materia' => (int) $subject->id_materia,
                    'id_gestion_periodo' => (int) $period->id_gestion_periodo,
                    'estado' => $offering->estado,
                ],
                $actorId
            );

            $offering->setRelation('career', $lockedCareer);
            $offering->setRelation('subject', $subject);
            $offering->setRelation('period', $this->activePeriod((int) $period->id_gestion_periodo));

            return $offering;
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

    private function assertPeriodIsActive(?Period $period): void
    {
        if ($period !== null && $period->estado === RecordStatus::ACTIVE) {
            return;
        }

        throw ValidationException::withMessages([
            'id_periodo' => [
                'El período seleccionado no existe o no está activo.',
            ],
        ]);
    }

    private function assertPeriodIsCurrent(Period $period): void
    {
        if ($period->vigente) {
            return;
        }

        throw ValidationException::withMessages([
            'id_periodo' => [
                'Solo se pueden asignar materias en el período vigente.',
            ],
        ]);
    }

    private function assertOfferingDoesNotExist(int $careerId, int $subjectId, int $periodId): void
    {
        $exists = AcademicOffering::query()
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->where('id_gestion_periodo', $periodId)
            ->exists();

        if ($exists) {
            throw $this->duplicateOffering();
        }
    }

    private function duplicateOffering(): ValidationException
    {
        return ValidationException::withMessages([
            'id_materia' => [
                'La materia ya está asignada a la carrera en este período.',
            ],
        ]);
    }
}
