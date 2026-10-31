<?php

namespace Tests\Unit\Models;

use App\Models\EntryAttempt;
use App\Models\Exam;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

class EntryAttemptTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;

    public function test_usa_la_tabla_y_la_clave_primaria_de_la_base(): void
    {
        $attempt = new EntryAttempt();

        $this->assertSame('intento_ingreso', $attempt->getTable());
        $this->assertSame('id_intento', $attempt->getKeyName());
        $this->assertTrue($attempt->getIncrementing());
        $this->assertFalse($attempt->usesTimestamps());
    }

    public function test_define_sus_columnas_asignables(): void
    {
        $this->assertSame([
            'id_examen',
            'id_estudiante',
            'cod_sis',
            'ci_presentado',
            'id_ambiente',
            'id_usuario_controlador',
            'motivo',
            'observacion',
            'registrado_en',
        ], (new EntryAttempt())->getFillable());
    }

    public function test_castea_ids_a_entero_y_la_fecha_a_carbon(): void
    {
        $context = $this->createReportingContext();
        $id = $this->insertEntryAttempt($context);

        $attempt = EntryAttempt::findOrFail($id);

        $this->assertIsInt($attempt->id_intento);
        $this->assertIsInt($attempt->id_examen);
        $this->assertIsInt($attempt->id_estudiante);
        $this->assertIsInt($attempt->id_ambiente);
        $this->assertIsString($attempt->id_usuario_controlador);
        $this->assertInstanceOf(Carbon::class, $attempt->registrado_en);
        $this->assertSame('2026-10-01 08:00:00', $attempt->registrado_en->copy()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_mapea_sus_relaciones(): void
    {
        $context = $this->createReportingContext();
        $attemptId = $this->insertEntryAttempt($context);
        $reportId = $this->insertStudentReport($context, ['id_intento' => $attemptId]);

        $attempt = EntryAttempt::with(['exam', 'student', 'controller', 'studentReport'])->findOrFail($attemptId);

        $this->assertInstanceOf(Exam::class, $attempt->exam);
        $this->assertSame($context['id_examen'], $attempt->exam->id_examen);
        $this->assertInstanceOf(Student::class, $attempt->student);
        $this->assertSame($context['id_estudiante'], $attempt->student->id_estudiante);
        $this->assertInstanceOf(User::class, $attempt->controller);
        $this->assertSame($context['id_usuario'], $attempt->controller->id_usuario);
        $this->assertInstanceOf(StudentReport::class, $attempt->studentReport);
        $this->assertSame($reportId, $attempt->studentReport->id_reporte_est);
    }

    public function test_un_intento_sin_estudiante_ni_reporte_tiene_relaciones_nulas(): void
    {
        $context = $this->createReportingContext();
        $attemptId = $this->insertEntryAttempt($context, [
            'id_estudiante' => null,
            'cod_sis' => '202199999',
            'motivo' => 'NO_ENCONTRADO',
        ]);

        $attempt = EntryAttempt::findOrFail($attemptId);

        $this->assertNull($attempt->id_estudiante);
        $this->assertNull($attempt->student);
        $this->assertNull($attempt->studentReport);
    }
}
