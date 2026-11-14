<?php

namespace App\Console;

use App\Jobs\FinalizeExpiredExamsJob;
use App\Jobs\OpenEntryControlJob;
use App\Services\Exams\ExamLifecycleService;
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
            // Misma programación de apertura: iniciar al cumplirse los diez minutos.
            app(ExamLifecycleService::class)->startExpired();
        })->name('open-entry-control')->everyMinute()->withoutOverlapping(2);

        $schedule->call(function (): void {
            app(FinalizeExpiredExamsJob::class)->handle(app(ExamLifecycleService::class));
        })->name('finalize-expired-exams')->everyMinute()->withoutOverlapping(2);

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
