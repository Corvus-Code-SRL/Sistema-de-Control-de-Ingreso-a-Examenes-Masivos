<?php

namespace Tests\Concerns;

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\PeriodType;
use App\Support\RecordStatus;

/**
 * Deja un período activo (gestion_periodo.activo) para las pruebas que no arman el catálogo
 * académico completo pero llaman endpoints que resuelven el período vigente.
 */
trait ActivatesPeriod
{
    /** Crea (o reutiliza) la gestión, crea el período y lo marca activo. Devuelve su id. */
    protected function activatePeriod(int $year = 2026, int $code = 1): int
    {
        Period::query()->where('activo', true)->update(['activo' => false]);

        $yearId = AcademicYear::query()->firstOrCreate(
            ['anio' => $year],
            ['estado' => RecordStatus::ACTIVE]
        )->id_gestion;

        return Period::create([
            'id_gestion' => $yearId,
            'id_periodo' => PeriodType::query()->where('codigo', $code)->value('id_periodo'),
            'activo' => true,
            'estado' => RecordStatus::ACTIVE,
        ])->id_gestion_periodo;
    }
}
