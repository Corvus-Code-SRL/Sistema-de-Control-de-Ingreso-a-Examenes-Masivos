<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Exceptions\Exams\ExamWarningsException;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Group;
use App\Services\Academic\SubjectCatalogService;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Crea, modifica y cancela exámenes (HU-24).
 *
 * El examen pertenece a un par materia-carrera y queda asociado al docente que lo
 * crea. Solo mientras está PROGRAMADO admite cambios o cancelación: desde que se
 * abre el control de ingreso su información general queda fija.
 */
class ExamService
{
    private const DEFAULT_CATEGORY = 'REGULAR';

    private SubjectCatalogService $subjectCatalog;

    private AuditLogService $auditLog;

    private ExamGroupService $groupService;

    public function __construct(
        SubjectCatalogService $subjectCatalog,
        AuditLogService $auditLog,
        ExamGroupService $groupService
    ) {
        $this->subjectCatalog = $subjectCatalog;
        $this->auditLog = $auditLog;
        $this->groupService = $groupService;
    }

    /**
     * Catálogos del formulario: pares materia-carrera activos, ambientes activos y los
     * grupos del docente en el periodo activo.
     */
    public function formOptions(): array
    {
        $pairs = $this->subjectCatalog->listSubjectCareers()['pairs']
            ->filter(function ($pair) {
                return $pair->estado === RecordStatus::ACTIVE
                    && $pair->subject->estado === RecordStatus::ACTIVE;
            })
            ->values();

        $classrooms = Classroom::query()
            ->where('estado', RecordStatus::ACTIVE)
            ->orderBy('nro_aula')
            ->get();

        $groups = Group::query()
            ->withActiveStudentCount()
            ->where('id_usuario_docente', $this->currentTeacherId())
            ->where('id_periodo', $this->subjectCatalog->activePeriodId())
            ->where('estado', RecordStatus::ACTIVE)
            ->orderBy('id_carrera')
            ->orderBy('id_materia')
            ->orderBy('num_grupo')
            ->get();

        return [
            'pairs'      => $pairs,
            'classrooms' => $classrooms,
            'groups'     => $groups,
        ];
    }

    public function create(array $data): Exam
    {
        $teacherId = $this->currentTeacherId();

        $exam = DB::transaction(function () use ($data, $teacherId) {
            $attributes = $this->prepareAttributes($data);

            $this->lockSchedules($teacherId, $data['ambientes']);
            $this->assertNoUnconfirmedWarnings($data, $teacherId, null);

            $exam = Exam::create($attributes + [
                'id_usuario_docente' => $teacherId,
                'estado'             => Exam::PROGRAMADO,
            ]);

            $exam->classrooms()->attach($data['ambientes']);

            return $this->groupService->assignGroups($exam, $data['grupos']);
        });

        return $this->loadDetail($exam);
    }

    /**
     * Criterios 10 y 11: la información general solo cambia mientras el examen está
     * PROGRAMADO. Desde EN_INGRESO nombre, fecha, hora, duración y materia quedan fijos.
     */
    public function update(Exam $exam, array $data): Exam
    {
        $teacherId = $this->currentTeacherId();

        $exam = DB::transaction(function () use ($exam, $data, $teacherId) {
            $exam = $this->lockExam($exam);

            $this->assertOwnedBy($exam, $teacherId);

            if ($exam->estado !== Exam::PROGRAMADO) {
                throw new ExamStateException($this->notEditableMessage($exam->estado));
            }

            $attributes = $this->prepareAttributes($data);

            $this->lockSchedules($teacherId, $data['ambientes']);
            $this->assertNoUnconfirmedWarnings($data, $teacherId, $exam->id_examen);

            $exam->update($attributes);
            $exam->classrooms()->sync($data['ambientes']);

            return $exam;
        });

        return $this->loadDetail($exam);
    }

    /**
     * Criterio 12: se cancela solo mientras el control de ingreso no empezó. El examen
     * no se borra: pasa a CANCELADO y la bitácora guarda quién lo canceló y cuándo.
     */
    public function cancel(Exam $exam): Exam
    {
        $teacherId = $this->currentTeacherId();

        $exam = DB::transaction(function () use ($exam, $teacherId) {
            $exam = $this->lockExam($exam);

            $this->assertOwnedBy($exam, $teacherId);

            if ($exam->estado !== Exam::PROGRAMADO) {
                throw new ExamStateException($this->notCancellableMessage($exam->estado));
            }

            $exam->update(['estado' => Exam::CANCELADO]);

            $this->auditLog->registrar(
                'CANCELAR',
                'examen',
                ['id_examen' => $exam->id_examen, 'estado' => Exam::PROGRAMADO],
                ['id_examen' => $exam->id_examen, 'estado' => Exam::CANCELADO],
                $teacherId
            );

            return $exam;
        });

        return $this->loadDetail($exam);
    }

    /**
     * Mientras no haya autenticación, el docente es el de configuración, el mismo que
     * usan las consultas de materias y grupos (HU-16, HU-17).
     */
    private function currentTeacherId(): string
    {
        $teacherId = $this->subjectCatalog->teacherId();

        if ($teacherId === '') {
            throw new RuntimeException('No hay un docente configurado en SCIEM_DOCENTE_FIJO_ID.');
        }

        return $teacherId;
    }

    /**
     * Columnas del examen a partir de los datos validados. El par debe estar activo
     * (materia y materia_carrera, regla de HU-16) y el tipo sale del catálogo.
     */
    private function prepareAttributes(array $data): array
    {
        $this->subjectCatalog->findSelectablePair((int) $data['id_carrera'], (int) $data['id_materia']);

        $startsAt = $this->startsAt($data);

        return [
            'nombre_examen'  => $data['nombre_examen'],
            'fecha'          => $data['fecha'],
            'hora_inicio'    => $data['hora_inicio'],
            // Puede quedar menor que hora_inicio si el examen cruza la medianoche.
            'hora_fin'       => $startsAt->copy()->addMinutes((int) $data['duracion'])->format('H:i'),
            'duracion'       => (int) $data['duracion'],
            'normas'         => $data['normas'] ?? null,
            'id_tipo_examen' => $this->resolveExamTypeId($data['categoria'] ?? self::DEFAULT_CATEGORY),
            'id_carrera'     => (int) $data['id_carrera'],
            'id_materia'     => (int) $data['id_materia'],
        ];
    }

    /**
     * El tipo se toma del catálogo tipo_examen; crear un examen nunca crea tipos.
     */
    private function resolveExamTypeId(string $category): int
    {
        $typeId = ExamType::query()
            ->where('categoria', $category)
            ->orderBy('id_tipo_examen')
            ->value('id_tipo_examen');

        if ($typeId === null) {
            throw ValidationException::withMessages([
                'categoria' => ["No hay un tipo de examen registrado para la categoría {$category}."],
            ]);
        }

        return (int) $typeId;
    }

    /**
     * Serializa las altas y cambios que compiten por el mismo docente o los mismos
     * ambientes, para que dos peticiones simultáneas no pasen ambas la revisión de
     * superposición.
     */
    private function lockSchedules(string $teacherId, array $classroomIds): void
    {
        DB::table('usuario')->where('id_usuario', $teacherId)->lockForUpdate()->first();

        Classroom::query()
            ->whereIn('id_ambiente', $classroomIds)
            ->orderBy('id_ambiente')
            ->lockForUpdate()
            ->get();
    }

    private function lockExam(Exam $exam): Exam
    {
        return Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();
    }

    private function assertOwnedBy(Exam $exam, string $teacherId): void
    {
        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new ExamOwnershipException();
        }
    }

    /**
     * Criterios 6, 8 y 9: nombre duplicado en la fecha y superposición con otro examen
     * del docente o de un ambiente. Son advertencias: el examen se guarda si el docente
     * las confirma.
     */
    private function assertNoUnconfirmedWarnings(array $data, string $teacherId, ?int $ignoredExamId): void
    {
        if (! empty($data['confirmar_advertencias'])) {
            return;
        }

        $warnings = $this->detectWarnings($data, $teacherId, $ignoredExamId);

        if ($warnings !== []) {
            throw new ExamWarningsException($warnings);
        }
    }

    private function detectWarnings(array $data, string $teacherId, ?int $ignoredExamId): array
    {
        $startsAt = $this->startsAt($data);
        $endsAt = $startsAt->copy()->addMinutes((int) $data['duracion']);

        $warnings = [];

        $sameName = $this->activeExams($ignoredExamId)
            ->where('id_usuario_docente', $teacherId)
            ->whereDate('fecha', $data['fecha'])
            ->whereRaw('lower(nombre_examen) = lower(?)', [$data['nombre_examen']])
            ->exists();

        if ($sameName) {
            $warnings['nombre_duplicado'] = [
                "Ya tiene un examen llamado «{$data['nombre_examen']}» en la misma fecha.",
            ];
        }

        $teacherOverlaps = $this->overlapping($startsAt, $endsAt, $ignoredExamId)
            ->where('id_usuario_docente', $teacherId)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->pluck('nombre_examen');

        if ($teacherOverlaps->isNotEmpty()) {
            $warnings['superposicion_horario'] = $teacherOverlaps
                ->map(fn (string $name) => "Ya tiene el examen «{$name}» en un horario que se superpone.")
                ->all();
        }

        $classroomOverlaps = $this->overlapping($startsAt, $endsAt, $ignoredExamId)
            ->join('examen_ambiente', 'examen_ambiente.id_examen', '=', 'examen.id_examen')
            ->join('ambiente', 'ambiente.id_ambiente', '=', 'examen_ambiente.id_ambiente')
            ->whereIn('examen_ambiente.id_ambiente', $data['ambientes'])
            ->orderBy('ambiente.nro_aula')
            ->get(['ambiente.nro_aula', 'examen.nombre_examen']);

        if ($classroomOverlaps->isNotEmpty()) {
            $warnings['superposicion_ambiente'] = $classroomOverlaps
                ->map(fn ($row) => "El ambiente {$row->nro_aula} ya está reservado para "
                    . "«{$row->nombre_examen}» en ese horario.")
                ->all();
        }

        return $warnings;
    }

    /**
     * Exámenes cuyo intervalo [inicio, fin) se cruza con el dado.
     *
     * Se compara fecha + hora como timestamp, no la hora sola: así un examen de 23:00
     * a 01:00 choca con otro a las 00:30 del día siguiente. Como ninguno dura un día,
     * basta buscar desde la víspera del inicio hasta la fecha del fin.
     */
    private function overlapping(Carbon $startsAt, Carbon $endsAt, ?int $ignoredExamId): Builder
    {
        return $this->activeExams($ignoredExamId)
            ->whereBetween('examen.fecha', [
                $startsAt->copy()->subDay()->toDateString(),
                $endsAt->toDateString(),
            ])
            ->whereRaw('(examen.fecha + examen.hora_inicio) < ?::timestamp', [$endsAt->format('Y-m-d H:i:s')])
            ->whereRaw(
                '(examen.fecha + examen.hora_inicio + make_interval(mins => coalesce(examen.duracion, 0)))'
                . ' > ?::timestamp',
                [$startsAt->format('Y-m-d H:i:s')]
            );
    }

    /** Un examen cancelado ya no ocupa horario ni ambientes. */
    private function activeExams(?int $ignoredExamId): Builder
    {
        return Exam::query()
            ->where('examen.estado', '<>', Exam::CANCELADO)
            ->when($ignoredExamId !== null, function (Builder $query) use ($ignoredExamId) {
                $query->where('examen.id_examen', '<>', $ignoredExamId);
            });
    }

    private function startsAt(array $data): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $data['fecha'] . ' ' . $data['hora_inicio']);
    }

    private function loadDetail(Exam $exam): Exam
    {
        return $exam->fresh([
            'examType',
            'subject',
            'career',
            'classrooms',
            'groups' => fn ($query) => $query->withActiveStudentCount(),
        ]);
    }

    private function notEditableMessage(string $status): string
    {
        return $status === Exam::CANCELADO
            ? 'El examen está cancelado y ya no puede modificarse.'
            : 'El control de ingreso del examen ya se inició: su información general no puede modificarse.';
    }

    private function notCancellableMessage(string $status): string
    {
        return $status === Exam::CANCELADO
            ? 'El examen ya está cancelado.'
            : 'El control de ingreso del examen ya se inició: el examen no puede cancelarse.';
    }
}
