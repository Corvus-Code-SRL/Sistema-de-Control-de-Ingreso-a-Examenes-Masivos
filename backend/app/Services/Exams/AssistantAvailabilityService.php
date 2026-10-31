<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Comprueba que un auxiliar no controle aulas distintas en horarios superpuestos. */
class AssistantAvailabilityService
{
    /**
     * Debe ejecutarse dentro de una transacción que haya bloqueado primero
     * la fila de usuario del auxiliar con lockForUpdate().
     *
     * Con varios ambientes y sin asignación, la habilitación es provisional:
     * se validará al elegir el ambiente en HU-030.
     */
    public function assertAvailable(Exam $exam, string $assistantId, ?int $classroomId): void
    {
        $classroomIds = DB::table('examen_ambiente')
            ->where('id_examen', $exam->id_examen)
            ->pluck('id_ambiente');

        $effectiveRoomId = $classroomId !== null
            ? $classroomId
            : ($classroomIds->count() === 1 ? (int) $classroomIds->first() : null);

        // Si todavía no hay un aula concreta, se comprobará al asignarla.
        // Los exámenes legados sin duración no tienen intervalo comprobable.
        if ($effectiveRoomId === null || (int) $exam->duracion <= 0) {
            return;
        }

        $startsAt = Carbon::parse($exam->fecha->toDateString() . ' ' . $exam->hora_inicio);
        $endsAt = $startsAt->copy()->addMinutes((int) $exam->duracion);

        // Misma fórmula de intervalos [inicio, fin) utilizada por ExamService.
        $overlaps = DB::table('examen_auxiliar as ea')
            ->join('examen as e', 'e.id_examen', '=', 'ea.id_examen')
            ->where('ea.id_usuario', $assistantId)
            ->where('e.id_examen', '<>', $exam->id_examen)
            ->whereIn('e.estado', [Exam::PROGRAMADO, Exam::EN_INGRESO, Exam::EN_CURSO])
            ->whereBetween('e.fecha', [
                $startsAt->copy()->subDay()->toDateString(),
                $endsAt->toDateString(),
            ])
            ->whereRaw('(e.fecha + e.hora_inicio) < ?::timestamp', [
                $endsAt->format('Y-m-d H:i:s'),
            ])
            ->whereRaw(
                '(e.fecha + e.hora_inicio + make_interval(mins => coalesce(e.duracion, 0))) > ?::timestamp',
                [$startsAt->format('Y-m-d H:i:s')]
            )
            ->get(['e.id_examen', 'e.nombre_examen', 'ea.id_ambiente']);

        foreach ($overlaps as $other) {
            $otherRooms = DB::table('examen_ambiente')
                ->where('id_examen', $other->id_examen)
                ->pluck('id_ambiente');

            $otherRoomId = $other->id_ambiente !== null
                ? (int) $other->id_ambiente
                : ($otherRooms->count() === 1 ? (int) $otherRooms->first() : null);

            // Otro examen sin ambiente definido: no inferir una ubicación falsa.
            if ($otherRoomId === null || $otherRoomId === $effectiveRoomId) {
                continue;
            }

            $field = $classroomId === null ? 'id_usuario' : 'id_ambiente';

            throw ValidationException::withMessages([
                $field => [
                    'El auxiliar ya está asignado a otro examen en un ambiente diferente '
                    . 'durante un horario que se superpone.',
                ],
            ]);
        }
    }
}
