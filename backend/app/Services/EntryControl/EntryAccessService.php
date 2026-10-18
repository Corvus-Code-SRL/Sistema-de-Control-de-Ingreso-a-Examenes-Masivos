<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\User;
use App\Exceptions\Exams\ExamStateException;
use App\Services\Exams\ExamTimingService;
use App\Support\Cache\EntryOpeningFailureStore;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class EntryAccessService
{
    private ExamTimingService $timing;
    private EntryOpeningFailureStore $openingFailures;

    public function __construct(ExamTimingService $timing, EntryOpeningFailureStore $openingFailures)
    {
        $this->timing = $timing;
        $this->openingFailures = $openingFailures;
    }

    public function openExamsFor(User $actor): array
    {
        if ($actor->estado !== User::ESTADO_ACTIVO) {
            return [];
        }

        $now = Carbon::now(config('sciem.zona_horaria'))->format('Y-m-d H:i:s');

        $exams = Exam::query()
            ->with(['subject', 'career', 'classrooms'])
            ->whereIn('estado', [Exam::PROGRAMADO, Exam::EN_INGRESO])
            ->whereNotNull('duracion')
            ->whereRaw(
                '(examen.fecha + examen.hora_inicio + make_interval(mins => examen.duracion)) > ?::timestamp',
                [$now]
            )
            ->where(function ($query) use ($actor): void {
                $query->where('id_usuario_docente', $actor->id_usuario)
                    ->orWhereExists(function ($subquery) use ($actor): void {
                        $subquery->selectRaw('1')
                            ->from('examen_auxiliar')
                            ->whereColumn('examen_auxiliar.id_examen', 'examen.id_examen')
                            ->where('examen_auxiliar.id_usuario', $actor->id_usuario)
                            ->where(function ($room): void {
                                // Sin ambiente asignado solo controla si el examen tiene uno solo.
                                $room->whereNotNull('examen_auxiliar.id_ambiente')
                                    ->orWhereRaw(
                                        '(select count(*) from examen_ambiente '
                                        . 'where examen_ambiente.id_examen = examen.id_examen) = 1'
                                    );
                            });
                    });
            })
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $examIds = $exams->pluck('id_examen')->all();
        $assistantAssignments = DB::table('examen_auxiliar')
            ->whereIn('id_examen', $examIds)
            ->where('id_usuario', $actor->id_usuario)
            ->get(['id_examen', 'id_ambiente'])
            ->keyBy('id_examen');
        $rosterTotals = DB::table('grupo_examen as gx')
            ->join('grupo_estudiante as gs', 'gs.id_grupo', '=', 'gx.id_grupo')
            ->whereIn('gx.id_examen', $examIds)
            ->selectRaw('gx.id_examen, count(distinct gs.id_estudiante) as total')
            ->groupBy('gx.id_examen')
            ->pluck('total', 'id_examen');
        $enteredTotals = DB::table('examen_estudiante as ee')
            ->join('grupo_examen as gx', 'gx.id_examen', '=', 'ee.id_examen')
            ->join('grupo_estudiante as gs', function ($join): void {
                $join->on('gs.id_grupo', '=', 'gx.id_grupo')
                    ->on('gs.id_estudiante', '=', 'ee.id_estudiante');
            })
            ->whereIn('ee.id_examen', $examIds)
            ->whereIn('ee.estado_ingreso', ['INGRESO', 'CON_RETRASO'])
            ->selectRaw('ee.id_examen, count(distinct ee.id_estudiante) as total')
            ->groupBy('ee.id_examen')
            ->pluck('total', 'id_examen');

        return $exams->map(function (Exam $exam) use (
            $actor, $assistantAssignments, $rosterTotals, $enteredTotals
        ): array {
            $examId = (int) $exam->id_examen;
            $total = (int) ($rosterTotals[$examId] ?? 0);
            $entered = (int) ($enteredTotals[$examId] ?? 0);
            $isTeacher = (string) $exam->id_usuario_docente === (string) $actor->id_usuario;
            $openingAt = $this->timing->startsAt($exam)
                ->subMinutes((int) $exam->minutos_apertura);
            $assignedRoomId = $isTeacher ? null : EntryControlSnapshotService::controlRoomOf(
                $assistantAssignments[$examId]->id_ambiente === null
                    ? null
                    : (int) $assistantAssignments[$examId]->id_ambiente,
                $exam->classrooms->map(fn ($room) => ['id_ambiente' => (int) $room->id_ambiente])->all()
            );

            return [
                'id_examen' => $examId,
                'nombre_examen' => $exam->nombre_examen,
                'estado' => $exam->estado,
                'fecha' => $exam->fecha ? $exam->fecha->format('Y-m-d') : null,
                'hora_inicio' => $exam->hora_inicio ? substr($exam->hora_inicio, 0, 5) : null,
                'materia' => $exam->subject ? $exam->subject->nombre : null,
                'carrera' => $exam->career ? $exam->career->nombre : null,
                'ambientes' => $exam->classrooms->pluck('nro_aula')->values()->all(),
                'rol_controlador' => $isTeacher ? 'DOCENTE' : 'AUXILIAR',
                'id_ambiente_asignado' => $assignedRoomId,
                'ambiente_asignado' => $assignedRoomId === null
                    ? null
                    : $exam->classrooms->firstWhere('id_ambiente', $assignedRoomId)?->nro_aula,
                'apertura_programada_en' => $openingAt->toIso8601String(),
                'motivo_apertura' => $exam->estado === Exam::PROGRAMADO
                    ? $this->openingFailures->get($examId)
                    : null,
                'total' => $total,
                'ingresados' => $entered,
                'pendientes' => max(0, $total - $entered),
            ];
        })->all();
    }

    public function context(Exam $exam, User $actor): array
    {
        $this->assertCanControl($exam, $actor);

        $isTeacher = (string) $exam->id_usuario_docente === (string) $actor->id_usuario;
        $assignedRoomId = $isTeacher ? null : $this->assistantRoomId($exam, $actor);

        $exam->loadMissing(['subject', 'career', 'classrooms', 'groups']);

        return [
            'id_examen' => (int) $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'fecha' => $exam->fecha ? $exam->fecha->format('Y-m-d') : null,
            'hora_inicio' => $exam->hora_inicio ? substr($exam->hora_inicio, 0, 5) : null,
            'hora_fin' => $exam->hora_fin ? substr($exam->hora_fin, 0, 5) : null,
            'estado' => $exam->estado,
            'materia' => $exam->subject ? $exam->subject->nombre : null,
            'carrera' => $exam->career ? $exam->career->nombre : null,
            'grupos' => $exam->groups->pluck('num_grupo')->values()->all(),
            'rol_controlador' => $isTeacher ? 'DOCENTE' : 'AUXILIAR',
            'id_ambiente_asignado' => $assignedRoomId,
            'ambientes' => $exam->classrooms->map(fn ($room) => [
                'id_ambiente' => (int) $room->id_ambiente,
                'nro_aula' => $room->nro_aula,
            ])->values()->all(),
        ];
    }

    public function assertCanControl(Exam $exam, User $actor): void
    {
        if ($actor->estado !== User::ESTADO_ACTIVO || $exam->id_carrera === null) {
            throw new AuthorizationException('No tiene permiso para controlar este examen.');
        }

        $isTeacher = (string) $exam->id_usuario_docente === (string) $actor->id_usuario;

        if (! $isTeacher) {
            $enablement = DB::table('examen_auxiliar')
                ->where('id_examen', $exam->id_examen)
                ->where('id_usuario', $actor->id_usuario)
                ->first(['id_ambiente']);

            if ($enablement === null) {
                throw new AuthorizationException('No tiene permiso para controlar este examen.');
            }

            if ($this->assistantRoomId($exam, $actor) === null) {
                throw new AuthorizationException(EntryControlSnapshotService::UNASSIGNED_ROOM_MESSAGE);
            }
        }

        if (! in_array($exam->estado, [Exam::PROGRAMADO, Exam::EN_INGRESO], true)
            || $exam->duracion === null
            || $this->timing->hasEnded($exam)) {
            throw new ExamStateException('El tiempo de control de ingreso del examen ya terminó.');
        }
    }

    /**
     * Ambiente donde controla el auxiliar: el que le asignó el docente o, si no tiene y
     * el examen tiene un solo ambiente, ese. Se resuelve al consultar y nunca se escribe.
     */
    private function assistantRoomId(Exam $exam, User $actor): ?int
    {
        $assigned = DB::table('examen_auxiliar')
            ->where('id_examen', $exam->id_examen)
            ->where('id_usuario', $actor->id_usuario)
            ->value('id_ambiente');

        $rooms = DB::table('examen_ambiente')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_ambiente')
            ->get(['id_ambiente'])
            ->map(fn ($room) => ['id_ambiente' => (int) $room->id_ambiente])
            ->all();

        return EntryControlSnapshotService::controlRoomOf($assigned === null ? null : (int) $assigned, $rooms);
    }
}
