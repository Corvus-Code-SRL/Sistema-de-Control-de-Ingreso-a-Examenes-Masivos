<?php

namespace App\Jobs;

use App\Exceptions\EntryControl\EntryOpeningException;
use App\Models\Exam;
use App\Services\EntryControl\RoomAssignmentService;
use App\Support\Cache\EntryOpeningFailureStore;
use Carbon\Carbon;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Valida la capacidad y abre el ingreso en una sola transacción. */
class OpenEntryControlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RoomAssignmentService $assignments, EntryOpeningFailureStore $failures): void
    {
        $timezone = config('sciem.zona_horaria');
        $now = Carbon::now($timezone);

        $exams = Exam::query()
            ->whereIn('estado', [Exam::PROGRAMADO, Exam::EN_INGRESO])
            ->whereBetween('fecha', [
                $now->copy()->subDay()->toDateString(),
                $now->copy()->addDay()->toDateString(),
            ])
            ->get();

        foreach ($exams as $exam) {
            if ($exam->id_carrera === null || $exam->duracion === null) {
                continue;
            }

            $startsAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                $exam->fecha->toDateString() . ' ' . substr($exam->hora_inicio, 0, 5),
                $timezone
            );

            if ($now->lessThan($startsAt->copy()->subMinutes((int) $exam->minutos_apertura))
                || $now->greaterThanOrEqualTo($startsAt->copy()->addMinutes((int) $exam->duracion))) {
                continue;
            }

            if ($exam->estado === Exam::EN_INGRESO
                && $assignments->hasPreparedSnapshot((int) $exam->id_examen)) {
                continue;
            }

            try {
                $assignments->prepare((int) $exam->id_examen);
                $failures->forget((int) $exam->id_examen);
            } catch (Throwable $exception) {
                if ($exception instanceof EntryOpeningException) {
                    try {
                        $failures->put(
                            (int) $exam->id_examen,
                            $exception->reason(),
                            $exception->getMessage(),
                            $startsAt->copy()->addMinutes((int) $exam->duracion)
                        );
                    } catch (Throwable $cacheException) {
                        Log::warning('No se pudo guardar el motivo de apertura fallida', [
                            'id_examen' => $exam->id_examen,
                            'motivo' => $cacheException->getMessage(),
                        ]);
                    }
                }

                Log::warning('No se pudo abrir el control de ingreso', [
                    'id_examen' => $exam->id_examen,
                    'motivo' => $exception->getMessage(),
                ]);
            }
        }
    }
}
