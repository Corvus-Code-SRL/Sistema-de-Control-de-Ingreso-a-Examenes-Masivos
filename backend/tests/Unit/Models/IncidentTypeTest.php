<?php

namespace Tests\Unit\Models;

use App\Models\IncidentType;
use App\Models\StudentReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class IncidentTypeTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;

    public function test_usa_la_tabla_y_la_clave_primaria_de_la_base(): void
    {
        $type = new IncidentType();

        $this->assertSame('tipo_falta', $type->getTable());
        $this->assertSame('id_falta', $type->getKeyName());
        $this->assertFalse($type->usesTimestamps());
        $this->assertSame(['nombre', 'descripcion'], $type->getFillable());
    }

    public function test_guarda_nombre_y_descripcion(): void
    {
        $type = IncidentType::create(['nombre' => 'Copia', 'descripcion' => 'Uso de apuntes.']);

        $this->assertDatabaseHas('tipo_falta', [
            'id_falta' => $type->id_falta,
            'nombre' => 'Copia',
            'descripcion' => 'Uso de apuntes.',
        ]);
    }

    public function test_lista_los_reportes_de_estudiantes_de_su_tipo(): void
    {
        $context = $this->createReportingContext();
        $reportId = $this->insertStudentReport($context);

        $type = IncidentType::with('studentReports')->findOrFail($context['id_tipo_falta']);

        $this->assertCount(1, $type->studentReports);
        $this->assertInstanceOf(StudentReport::class, $type->studentReports->first());
        $this->assertSame($reportId, $type->studentReports->first()->id_reporte_est);
    }
}
