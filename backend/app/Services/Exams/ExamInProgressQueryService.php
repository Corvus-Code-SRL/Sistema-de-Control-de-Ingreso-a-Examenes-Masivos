<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Exams\Distribution\RoomDistributionReader;
use App\Services\Security\UserRoleService;
use App\Services\Incidents\ExamInProgressSituationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ExamInProgressQueryService
{
    public function __construct(
        private ExamParticipantService $participants,
        private ExamTimingService $timing,
        private UserRoleService $roles,
        private ExamInProgressSituationService $situations,
        private ExamCollaboratorService $collaborators,
        private RoomDistributionReader $distribution
    ) {}

    public function show(Exam $exam, User $actor): array
    {
        if ($actor->estado !== User::ESTADO_ACTIVO) {
            throw new AuthorizationException();
        }

        // Docente del examen: su creador o un colaborador que aceptó la invitación (HU-027).
        $isTeacher = $this->collaborators->isTeacherOf($exam, (string) $actor->id_usuario);

        if ($isTeacher) {
            if (!$this->roles->isTeacher($actor->id_usuario)) {
                throw new AuthorizationException();
            }
        } elseif (!$this->roles->hasAnyActiveRole(
            $actor->id_usuario,
            [Role::AUXILIAR]
        )) {
            throw new AuthorizationException();
        }

        $exam->loadMissing(['subject', 'classrooms']);

        $examId = (int) $exam->id_examen;

        $rooms = $exam->classrooms
            ->sortBy('id_ambiente')
            ->map(fn ($room) => [
                'id_ambiente' => (int) $room->id_ambiente,
                'nro_aula' => $room->nro_aula,
                'capacidad' => (int) $room->capacidad,
            ])
            ->values()
            ->all();

        $assignedRoomId = null;

        if (!$isTeacher) {
            $assignment = DB::table('examen_auxiliar')
                ->where('id_examen', $examId)
                ->where('id_usuario', $actor->id_usuario)
                ->first(['id_ambiente']);

            if ($assignment === null) {
                throw new AuthorizationException(
                    'No está habilitado para este examen.'
                );
            }

            $assignedRoomId =
                EntryControlSnapshotService::controlRoomOf(
                    $assignment->id_ambiente === null
                        ? null
                        : (int) $assignment->id_ambiente,
                    $rooms
                );

            if ($assignedRoomId === null) {
                throw new AuthorizationException(
                    'No tiene un ambiente asignado.'
                );
            }
        }

        if (!in_array($exam->estado, [Exam::EN_CURSO, Exam::FINALIZADO], true)) {
            throw new ExamStateException(
                'El examen todavía no está en curso.'
            );
        }

        $roster = DB::query()
            ->fromSub($this->participants->expected($examId), 'nomina')
            ->leftJoin('examen_estudiante as ee', function ($join) use ($examId) {
                $join->on(
                    'ee.id_estudiante',
                    '=',
                    'nomina.id_estudiante'
                )->where('ee.id_examen', '=', $examId);
            })
            ->select(
                'nomina.id_estudiante',
                'nomina.cod_sis',
                'nomina.ci',
                'nomina.nombre',
                'nomina.apellido_paterno',
                'nomina.apellido_materno',
                'nomina.id_grupo',
                'nomina.es_excepcional',
                'nomina.motivo_excepcional',
                'ee.estado_ingreso',
                'ee.estado_habilitacion',
                'ee.hora_ingreso',
                'ee.id_ambiente',
                'ee.expulsado_en'
            )
            ->orderBy('nomina.id_estudiante')
            ->get();

        // El reparto automático de aulas solo considera la nómina: el excepcional conserva el aula que se le eligió.
        $assignedRooms = $this->distribution->assignedRooms($examId)
            ?? EntryControlSnapshotService::assignRooms(
            $roster->reject(fn ($student) => (bool) $student->es_excepcional)
                ->pluck('id_estudiante')
                ->map(fn ($id) => (int) $id)
                ->all(),
            $rooms
        );

        $roomNames = collect($rooms)
            ->pluck('nro_aula', 'id_ambiente');

        $students = [];

        foreach ($roster as $student) {
            $studentId = (int) $student->id_estudiante;

            // El ambiente de ingreso prevalece sobre el asignado.
            // HU-057 podrá actualizarlo cuando haya un traslado.
            $roomId = $student->id_ambiente === null
                ? ($assignedRooms[$studentId]['id_ambiente'] ?? null)
                : (int) $student->id_ambiente;

            if (!$isTeacher && $roomId !== $assignedRoomId) {
                continue;
            }

            $students[] = [
                'id_estudiante' => $studentId,
                // Grupo por el que figura en la nómina; la vista lo usa si la lista va separada por grupo.
                'id_grupo' => $student->id_grupo === null ? null : (int) $student->id_grupo,
                'es_excepcional' => (bool) $student->es_excepcional,
                'motivo_excepcional' => $student->motivo_excepcional,
                'cod_sis' => $student->cod_sis,
                'ci' => $student->ci,
                'nombre_completo' => trim(implode(' ', array_filter([
                    $student->nombre,
                    $student->apellido_paterno,
                    $student->apellido_materno,
                ]))),
                'estado_ingreso' =>
                    $student->estado_ingreso ?? 'NO_INGRESO',
                'estado_habilitacion' =>
                    $student->estado_habilitacion ?? 'HABILITADO',
                'hora_ingreso' => $student->hora_ingreso === null
                    ? null
                    : substr($student->hora_ingreso, 0, 5),
                'expulsado_en' => $student->expulsado_en === null
                    ? null : \Carbon\Carbon::parse($student->expulsado_en)->utc()->toIso8601String(),
                'id_ambiente' => $roomId,
                'ambiente' => $roomId === null
                    ? null
                    : $roomNames->get($roomId),
            ];
        }

        $counts = [
            'a_tiempo' => 0,
            'con_retraso' => 0,
            'ausentes' => 0,
            'no_habilitados' => 0,
            'expulsados' => 0,
        ];

        foreach ($students as $student) {
            if ($student['expulsado_en'] !== null) {
                $counts['expulsados']++;
            } elseif (
                $student['estado_habilitacion'] === 'NO_HABILITADO'
                && $student['estado_ingreso'] === 'NO_INGRESO'
            ) {
                // Un no habilitado que no ingresó no es ausente: tiene su propio total.
                $counts['no_habilitados']++;
            } elseif ($student['estado_ingreso'] === 'INGRESO') {
                $counts['a_tiempo']++;
            } elseif ($student['estado_ingreso'] === 'CON_RETRASO') {
                $counts['con_retraso']++;
            } else {
                $counts['ausentes']++;
            }
        }

        $studentIds = array_column($students, 'id_estudiante');
        $incidentCounts = DB::table('reporte_estudiante')->where('id_examen', $examId)
            ->where('origen', 'EN_CURSO')->whereIn('id_estudiante', $studentIds)
            ->selectRaw('id_estudiante, count(*) as total')->groupBy('id_estudiante')->pluck('total', 'id_estudiante');
        $situationData = $this->situations->forExam($exam, $studentIds, $actor);

        foreach ($students as &$student) {
            $student['total_incidencias'] = (int) ($incidentCounts[$student['id_estudiante']] ?? 0);
            $student['situaciones'] = $situationData['students'][$student['id_estudiante']] ?? [
                'identidad_observada' => false,
                'ultimo_cambio_ambiente' => null,
            ];
        }
        unset($student);

        $incidents = DB::table('reporte_estudiante')
            ->where('id_examen', $examId)
            ->where('estado', 'PENDIENTE');

        if (!$isTeacher) {
            $incidents->whereIn('id_estudiante', $studentIds);
        }

        $counts['presentes'] =
            $counts['a_tiempo'] + $counts['con_retraso'];

        $counts['total'] = count($students);
        $counts['incidencias_pendientes'] = $incidents->count();

        return [
            'id_examen' => $examId,
            'nombre_examen' => $exam->nombre_examen,
            'materia' => $exam->subject?->nombre,
            'estado' => $exam->estado,
            'rol_controlador' =>
                $isTeacher ? 'DOCENTE' : 'AUXILIAR',
            'es_creador' => (string) $exam->id_usuario_docente === (string) $actor->id_usuario,
            'id_ambiente_asignado' => $assignedRoomId,
            'ambientes' => $rooms,
            'inicio_programado' =>
                $this->timing->startsAt($exam)->toIso8601String(),
            'ingreso_abierto_en' =>
                $exam->ingreso_abierto_en?->toIso8601String(),
            'ingreso_ordinario_cerrado_en' =>
                $exam->ingreso_ordinario_cerrado_en?->toIso8601String(),
            'fin_real_en' =>
                $exam->fin_real_en?->toIso8601String(),
            'fin_automatico' => (bool) $exam->fin_automatico,
            'fin_programado_en' =>
                $this->timing->endsAt($exam)->toIso8601String(),
            'hora_servidor_en' => now('UTC')->toIso8601String(),
            'totales' => $counts,
            'estudiantes' => $students,
            // HU-027: el docente que invitó pidió ver la lista separada por grupo (con su docente).
            'lista_por_grupo' => $this->collaborators->listsByGroup($examId),
            'grupos' => $this->collaborators->examGroups($examId),
            'incidencias_habilitadas' => $exam->id_carrera !== null,
            'situaciones_habilitadas' => $situationData['enabled'],
            'total_situaciones' => $situationData['revision'],
        ];
    }
}
