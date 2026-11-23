<?php

namespace App\Console;

use App\Jobs\FinalizeExpiredExamsJob;
use App\Jobs\OpenEntryControlJob;
use App\Services\Exams\ExamLifecycleService;
use App\Services\RiskCenter\Academic\RectificationExpiryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // La apertura corre cada minuto sin depender de un trabajador de cola.
        $schedule->call(function (): void {
            app()->call([app(OpenEntryControlJob::class), 'handle']);
        })->name('open-entry-control')->everyMinute()->withoutOverlapping(2);

        // El inicio programado no espera a que termine la apertura del control.
        $schedule->call(function (): void {
            app(ExamLifecycleService::class)->startExpired();
        })->name('start-expired-exams')->everyMinute()->withoutOverlapping(2);

        $schedule->call(function (): void {
            app(FinalizeExpiredExamsJob::class)->handle(app(ExamLifecycleService::class));
        })->name('finalize-expired-exams')->everyMinute()->withoutOverlapping(2);

        // HU-062: las solicitudes de rectificación sin respuesta pasan a VENCIDAS. No anula registros ni escribe
        // bitácora; las lecturas ya tratan como vencida a toda pendiente pasada de fecha, así que la frecuencia
        // no es crítica.
        $schedule->call(function (): void {
            app(RectificationExpiryService::class)->expireDue();
        })->name('expire-rectification-requests')->hourly()->withoutOverlapping(10);

        // Respaldo diario de la base: solo donde se encendió SCIEM_RESPALDO_DIARIO.
        if (config('sciem.respaldo.diario')) {
            $schedule->command('sciem:respaldar')
                ->name('respaldo-diario')
                ->dailyAt((string) config('sciem.respaldo.hora', '03:15'))
                ->timezone((string) config('sciem.zona_horaria', 'America/La_Paz'))
                ->withoutOverlapping(120);
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
