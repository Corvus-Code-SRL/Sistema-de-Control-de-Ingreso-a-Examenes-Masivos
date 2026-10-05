<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\User;
use App\Exceptions\Exams\ExamStateException;
use App\Services\Exams\ExamTimingService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class EntryAccessService
{
    private ExamTimingService $timing;

    public function __construct(ExamTimingService $timing)
    {
        $this->timing = $timing;
    }

    public function openExamsFor(User $actor): array
    {
        if ($actor->estado !== User::ESTADO_ACTIVO) {
            return [];
        }

        $now = Carbon::now(config('sciem.zona_horaria'))->format('Y-m-d H:i:s');

        return Exam::query()
            ->with(['subject', 'classrooms'])
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
            ->get()
            ->map(fn (Exam $exam) => [
                'id_examen' => (int) $exam->id_examen,
                'nombre_examen' => $exam->nombre_examen,
                'estado' => $exam->estado,
                'fecha' => $exam->fecha ? $exam->fecha->format('Y-m-d') : null,
                'hora_inicio' => $exam->hora_inicio ? substr($exam->hora_inicio, 0, 5) : null,
                'materia' => $exam->subject ? $exam->subject->nombre : null,
                'ambientes' => $exam->classrooms->pluck('nro_aula')->values()->all(),
            ])->all();
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
     * Ambiente donde controla el auxiliar: el que le asignó el docente (HU-09) o, si no tiene y
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
