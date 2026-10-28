<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\Role;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\Security\UserRoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ExamInProgressQueryService
{
    public function __construct(
        private ExamParticipantService $participants,
        private ExamTimingService $timing,
        private UserRoleService $roles
    ) {}

    public function show(Exam $exam, User $actor): array
    {
        if (!in_array($exam->estado, [
            Exam::EN_CURSO,
            Exam::FINALIZADO,
        ], true)) {
            throw new ExamStateException(
                'El examen todavía no está en curso.'
            );
        }

        if ($actor->estado !== User::ESTADO_ACTIVO) {
            throw new AuthorizationException();
        }

        $isTeacher = (string) $exam->id_usuario_docente
            === (string) $actor->id_usuario;

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
                'ee.estado_ingreso',
                'ee.hora_ingreso',
                'ee.id_ambiente'
            )
            ->orderBy('nomina.id_estudiante')
            ->get();

        $assignedRooms = EntryControlSnapshotService::assignRooms(
            $roster->pluck('id_estudiante')
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
                'cod_sis' => $student->cod_sis,
                'ci' => $student->ci,
                'nombre_completo' => trim(implode(' ', array_filter([
                    $student->nombre,
                    $student->apellido_paterno,
                    $student->apellido_materno,
                ]))),
                'estado_ingreso' =>
                    $student->estado_ingreso ?? 'NO_INGRESO',
                'hora_ingreso' => $student->hora_ingreso === null
                    ? null
                    : substr($student->hora_ingreso, 0, 5),
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
        ];

        foreach ($students as $student) {
            if ($student['estado_ingreso'] === 'INGRESO') {
                $counts['a_tiempo']++;
            } elseif ($student['estado_ingreso'] === 'CON_RETRASO') {
                $counts['con_retraso']++;
            } else {
                $counts['ausentes']++;
            }
        }

        $studentIds = array_column($students, 'id_estudiante');

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
            'id_ambiente_asignado' => $assignedRoomId,
            'ambientes' => $rooms,
            'inicio_real_en' =>
                $exam->inicio_real_en?->toIso8601String(),
            'fin_real_en' =>
                $exam->fin_real_en?->toIso8601String(),
            'inicio_automatico' => (bool) $exam->inicio_automatico,
            'fin_automatico' => (bool) $exam->fin_automatico,
            'fin_programado_en' =>
                $this->timing->endsAt($exam)->toIso8601String(),
            'totales' => $counts,
            'estudiantes' => $students,
        ];
    }
}
