<?php

namespace Tests\Concerns;

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\PeriodType;
use App\Support\RecordStatus;

/**
 * Deja un período vigente (gestion_periodo.vigente) para las pruebas que no arman el catálogo
 * académico completo pero llaman endpoints que resuelven el período vigente.
 */
trait ActivatesPeriod
{
    /** Crea (o reutiliza) la gestión, crea el período y lo marca vigente. Devuelve su id. */
    protected function activatePeriod(int $year = 2026, int $code = 1): int
    {
        Period::query()->where('vigente', true)->update(['vigente' => false]);

        $yearId = AcademicYear::query()->firstOrCreate(
            ['anio' => $year],
            ['estado' => RecordStatus::ACTIVE]
        )->id_gestion;

        return Period::create([
            'id_gestion' => $yearId,
            'id_periodo' => PeriodType::query()->where('codigo', $code)->value('id_periodo'),
            'vigente' => true,
            'estado' => RecordStatus::ACTIVE,
        ])->id_gestion_periodo;
    }
}
