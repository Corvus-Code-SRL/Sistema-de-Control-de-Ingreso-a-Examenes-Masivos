<?php

namespace Tests\Unit\Models;

use App\Models\EntryAttempt;
use App\Models\Exam;
use App\Models\IncidentType;
use App\Models\RiskCenter;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\User;
use App\Support\ReportStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class StudentReportTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;

    public function test_usa_la_tabla_y_la_clave_primaria_de_la_base(): void
    {
        $report = new StudentReport();

        $this->assertSame('reporte_estudiante', $report->getTable());
        $this->assertSame('id_reporte_est', $report->getKeyName());
        $this->assertFalse($report->usesTimestamps());
    }

    public function test_id_intento_es_asignable_y_se_castea_a_entero(): void
    {
        $this->assertContains('id_intento', (new StudentReport())->getFillable());

        $context = $this->createReportingContext();
        $attemptId = $this->insertEntryAttempt($context);
        $id = $this->insertStudentReport($context, ['id_intento' => $attemptId]);

        $this->assertSame($attemptId, StudentReport::findOrFail($id)->id_intento);
    }

    public function test_la_evidencia_puede_ser_nula(): void
    {
        $id = $this->insertStudentReport($this->createReportingContext());

        $report = StudentReport::findOrFail($id);

        $this->assertNull($report->evidencia_url);
        $this->assertNull($report->evidencia_public_id);
    }

    public function test_mapea_sus_relaciones(): void
    {
        $context = $this->createReportingContext();
        $attemptId = $this->insertEntryAttempt($context);
        $id = $this->insertStudentReport($context, ['id_intento' => $attemptId]);

        $report = StudentReport::with(['student', 'exam', 'incidentType', 'userReporter', 'entryAttempt'])
            ->findOrFail($id);

        $this->assertInstanceOf(Student::class, $report->student);
        $this->assertInstanceOf(Exam::class, $report->exam);
        $this->assertInstanceOf(IncidentType::class, $report->incidentType);
        $this->assertSame($context['id_tipo_falta'], $report->incidentType->id_falta);
        $this->assertInstanceOf(User::class, $report->userReporter);
        $this->assertInstanceOf(EntryAttempt::class, $report->entryAttempt);
        $this->assertSame($attemptId, $report->entryAttempt->id_intento);
        $this->assertNull($report->userReviewer);
        $this->assertNull($report->riskCenter);
    }

    public function test_un_reporte_sin_intento_no_tiene_intento_asociado(): void
    {
        $id = $this->insertStudentReport($this->createReportingContext());

        $this->assertNull(StudentReport::findOrFail($id)->entryAttempt);
    }

    public function test_el_reporte_aprobado_se_enlaza_con_su_fila_de_la_central(): void
    {
        $context = $this->createReportingContext();
        $id = $this->insertStudentReport($context, [
            'estado' => ReportStatus::APPROVED,
            'fecha_revision' => '2026-10-02 09:00:00+00',
            'id_usuario_revisor' => $context['id_usuario'],
        ]);
        DB::table('central_riesgo')->insert([
            'id_reporte_est' => $id,
            'id_estudiante' => $context['id_estudiante'],
        ]);

        $report = StudentReport::with('riskCenter')->findOrFail($id);

        $this->assertInstanceOf(RiskCenter::class, $report->riskCenter);
        $this->assertSame($context['id_estudiante'], $report->riskCenter->id_estudiante);
    }

    public function test_el_scope_pending_devuelve_solo_los_pendientes(): void
    {
        $context = $this->createReportingContext();
        $pending = $this->insertStudentReport($context);
        $this->insertStudentReport($context, [
            'estado' => ReportStatus::REJECTED,
            'fecha_revision' => '2026-10-02 09:00:00+00',
            'id_usuario_revisor' => $context['id_usuario'],
        ]);

        $this->assertSame(
            [$pending],
            StudentReport::pending()->where('id_estudiante', $context['id_estudiante'])
                ->pluck('id_reporte_est')->all()
        );
    }
}
