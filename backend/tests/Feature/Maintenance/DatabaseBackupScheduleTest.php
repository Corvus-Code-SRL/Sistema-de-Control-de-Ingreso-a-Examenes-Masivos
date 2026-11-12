<?php

namespace Tests\Feature\Maintenance;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionMethod;
use Tests\TestCase;

/** El respaldo diario solo se programa donde se encendió SCIEM_RESPALDO_DIARIO. */
class DatabaseBackupScheduleTest extends TestCase
{
    /** @return array<int, \Illuminate\Console\Scheduling\Event> */
    private function backupEvents(): array
    {
        $schedule = new Schedule();
        $method = new ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke($this->app->make(Kernel::class), $schedule);

        return array_values(array_filter(
            $schedule->events(),
            fn ($event) => strpos((string) $event->command, 'sciem:respaldar') !== false
        ));
    }

    public function test_el_respaldo_diario_se_programa_solo_si_se_enciende(): void
    {
        config(['sciem.respaldo.diario' => false]);
        $this->assertSame([], $this->backupEvents());

        config(['sciem.respaldo.diario' => true, 'sciem.respaldo.hora' => '03:15']);
        [$event] = $this->backupEvents();

        $this->assertSame('15 3 * * *', $event->expression);
        $this->assertSame('America/La_Paz', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}
