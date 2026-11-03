<?php

namespace App\Jobs;

use App\Services\Exams\ExamLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Cierra controles vencidos y finaliza exámenes diez minutos después de su fin programado
 * (dos horas si nunca se iniciaron).
 */
class FinalizeExpiredExamsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExamLifecycleService $lifecycle): void
    {
        $lifecycle->finishExpired();
    }
}
