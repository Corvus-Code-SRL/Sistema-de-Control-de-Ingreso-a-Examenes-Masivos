<?php

namespace Tests\Unit\Models;

use App\Models\RiskCenter;
use App\Models\Student;
use App\Models\StudentReport;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class RiskCenterTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;

    public function test_usa_la_tabla_y_la_clave_primaria_de_la_base(): void
    {
        $record = new RiskCenter();

        $this->assertSame('central_riesgo', $record->getTable());
        $this->assertSame('id_cr_estudiante', $record->getKeyName());
        $this->assertFalse($record->usesTimestamps());
        $this->assertSame(['id_reporte_est', 'id_estudiante', 'fecha_registro'], $record->getFillable());
    }

    public function test_castea_sus_columnas_y_enlaza_reporte_y_estudiante(): void
    {
        $context = $this->createReportingContext();
        $reportId = $this->insertStudentReport($context, [
            'estado' => ReportStatus::APPROVED,
            'fecha_revision' => '2026-10-02 09:00:00+00',
            'id_usuario_revisor' => $context['id_usuario'],
        ]);

        $record = RiskCenter::create([
            'id_reporte_est' => $reportId,
            'id_estudiante' => $context['id_estudiante'],
            'fecha_registro' => '2026-10-02 09:00:00+00',
        ])->load(['studentReport', 'student']);

        $this->assertIsInt($record->id_cr_estudiante);
        $this->assertIsInt($record->id_reporte_est);
        $this->assertIsInt($record->id_estudiante);
        $this->assertInstanceOf(Carbon::class, $record->fecha_registro);
        $this->assertInstanceOf(StudentReport::class, $record->studentReport);
        $this->assertSame($reportId, $record->studentReport->id_reporte_est);
        $this->assertInstanceOf(Student::class, $record->student);
        $this->assertSame($context['id_estudiante'], $record->student->id_estudiante);
    }
}
