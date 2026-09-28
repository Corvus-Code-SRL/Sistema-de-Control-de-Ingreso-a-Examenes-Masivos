<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Pasa los examenes a estado EN_INGRESO N minutos antes de su inicio.
 */
class AbrirControlIngresoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        DB::transaction(function () {
            DB::table('examen')
                ->where('estado', 'PROGRAMADO')
                ->whereRaw("(fecha + hora_inicio) - INTERVAL '10 minutes' <= NOW()")
                ->update(['estado' => 'EN_INGRESO']);
        });
    }
}