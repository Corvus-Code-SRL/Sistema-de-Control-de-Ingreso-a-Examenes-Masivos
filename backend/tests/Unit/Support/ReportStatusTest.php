<?php

namespace Tests\Unit\Support;

use App\Support\ReportStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_las_constantes_son_los_valores_del_enum_estado_reporte(): void
    {
        $this->assertSame('PENDIENTE', ReportStatus::PENDING);
        $this->assertSame('APROBADO', ReportStatus::APPROVED);
        $this->assertSame('RECHAZADO', ReportStatus::REJECTED);
    }

    public function test_coinciden_con_los_valores_del_enum_de_la_base(): void
    {
        $labels = DB::table('pg_enum')
            ->join('pg_type', 'pg_type.oid', '=', 'pg_enum.enumtypid')
            ->where('pg_type.typname', 'estado_reporte')
            ->orderBy('pg_enum.enumsortorder')
            ->pluck('pg_enum.enumlabel')
            ->all();

        $this->assertSame(
            [ReportStatus::PENDING, ReportStatus::APPROVED, ReportStatus::REJECTED],
            $labels
        );
    }
}
