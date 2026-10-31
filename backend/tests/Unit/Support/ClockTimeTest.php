<?php

namespace Tests\Unit\Support;

use App\Support\ClockTime;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClockTimeTest extends TestCase
{
    public function test_recorta_la_hora_de_la_base_a_hh_mm(): void
    {
        $this->assertSame('10:30', ClockTime::fromTime('10:30:45'));
        $this->assertSame('07:05', ClockTime::fromTime('07:05:00'));
    }

    public function test_un_instante_se_muestra_en_la_zona_del_sistema(): void
    {
        config(['sciem.zona_horaria' => 'America/La_Paz']);
        $instant = Carbon::parse('2026-10-31 14:12:30', 'UTC');

        $this->assertSame('10:12', ClockTime::local($instant));
        $this->assertSame('14:12:30', $instant->format('H:i:s'));
    }
}
