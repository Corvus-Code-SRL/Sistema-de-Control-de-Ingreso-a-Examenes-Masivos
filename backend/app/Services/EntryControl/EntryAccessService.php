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
                            ->whereNotNull('examen_auxiliar.id_ambiente');
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
        $assignedRoomId = $isTeacher ? null : (int) DB::table('examen_auxiliar')
            ->where('id_examen', $exam->id_examen)
            ->where('id_usuario', $actor->id_usuario)
            ->value('id_ambiente');

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
        $isAssistant = ! $isTeacher && DB::table('examen_auxiliar')
            ->where('id_examen', $exam->id_examen)
            ->where('id_usuario', $actor->id_usuario)
            ->whereNotNull('id_ambiente')
            ->exists();

        if (! $isTeacher && ! $isAssistant) {
            throw new AuthorizationException('No tiene permiso para controlar este examen.');
        }

        if (! in_array($exam->estado, [Exam::PROGRAMADO, Exam::EN_INGRESO], true)
            || $exam->duracion === null
            || $this->timing->hasEnded($exam)) {
            throw new ExamStateException('El tiempo de control de ingreso del examen ya terminó.');
        }
    }
}
