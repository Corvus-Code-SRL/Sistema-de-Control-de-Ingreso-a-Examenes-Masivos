<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Exams\ExamParticipantService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class BackfillExamenEstudianteFinalizadosTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_migracion_recupera_esperados_sin_duplicar_ni_modificar_ingresos(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::FINALIZADO,
        ], [$this->aulaId]);

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $students = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->pluck('id_estudiante');

        $this->assertCount(2, $students);

        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $students[0],
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '10:00:00',
        ]);

        require_once database_path(
           'migrations/2026_10_19_213704_backfill_examen_estudiante_finalizados.php'
        );

        $migration = new \BackfillExamenEstudianteFinalizados();

        $migration->up();
        $migration->up();
        $this->assertSame(
            2,
            DB::table('examen_estudiante')
                ->where('id_examen', $exam->id_examen)
                ->count()
        );

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $students[0],
            'estado_ingreso' => 'INGRESO',
        ]);

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $students[1],
            'estado_ingreso' => 'NO_INGRESO',
            'estado_habilitacion' => 'HABILITADO',
        ]);

        $counts = app(ExamParticipantService::class)
            ->counts((int) $exam->id_examen);

        $this->assertSame(2, $counts['esperados']);
    }
}