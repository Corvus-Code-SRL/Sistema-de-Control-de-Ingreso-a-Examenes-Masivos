<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Exams\Importers\EligibleListPreviewResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamEligibleListPreviewTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use BuildsRosterFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
        $this->actAsTeacher($this->docenteId);
    }

    public function test_preview_devuelve_200_con_archivo_valido(): void
    {
        $exam = $this->examWithOwnRoster(2);

        $this->previewCsv($exam, ['300000001', '300000002'])
            ->assertOk()
            ->assertJsonPath('data.total_filas', 2)
            ->assertJsonPath('data.filas_validas', 2)
            ->assertJsonPath('data.filas_inconsistentes', 0)
            ->assertJsonPath('data.faltantes', 0)
            ->assertJsonPath('data.filas.0.codigo_sis', '300000001')
            ->assertJsonPath('data.filas.0.estado', EligibleListPreviewResult::MATCHED)
            ->assertJsonPath('data.filas.1.codigo_sis', '300000002')
            ->assertJsonPath('data.filas.1.estado', EligibleListPreviewResult::MATCHED);
    }

    public function test_preview_marca_no_encontrado_si_el_cod_sis_no_esta_en_examen_estudiante(): void
    {
        $exam = $this->examWithOwnRoster(1);

        $this->previewCsv($exam, ['300000001', '300000099'])
            ->assertOk()
            ->assertJsonPath('data.filas.1.estado', EligibleListPreviewResult::NOT_FOUND_IN_EXAM);
    }

    public function test_preview_rechaza_extension_no_permitida(): void
    {
        $exam = $this->createExamWithRoster();

        $file = UploadedFile::fake()->create('lista.pdf', 10, 'application/pdf');

        $this->post("/api/examenes/{$exam->id_examen}/habilitados/preview", ['archivo' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_preview_rechaza_archivo_mayor_a_10_mb(): void
    {
        $exam = $this->createExamWithRoster();

        $file = UploadedFile::fake()->create('lista.csv', 10241, 'text/csv');

        $this->post("/api/examenes/{$exam->id_examen}/habilitados/preview", ['archivo' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_preview_sin_archivo(): void
    {
        $exam = $this->createExamWithRoster();

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/preview", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_preview_examen_inexistente_devuelve_404(): void
    {
        $this->previewCsvById(999999, ['300000001'])
            ->assertNotFound();
    }

    public function test_preview_examen_de_otro_docente_devuelve_403(): void
    {
        $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);
        $this->seedRoster($exam, 1);

        $this->previewCsv($exam, ['300000001'])
            ->assertForbidden();
    }

    public function test_preview_examen_en_ingreso_devuelve_409(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);
        $this->seedRoster($exam, 1);

        $this->previewCsv($exam, ['300000001'])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El control de ingreso ya empezó. La lista de habilitados es de solo lectura.'
            );
    }

    /**
     * @param array<int, string> $sisCodes
     */
    private function previewCsv(Exam $exam, array $sisCodes)
    {
        return $this->previewCsvById($exam->id_examen, $sisCodes);
    }

    /**
     * @param array<int, string> $sisCodes
     */
    private function previewCsvById(int $examId, array $sisCodes)
    {
        $rows = array_map(
            static fn (string $sis): array => [$sis, 'APELLIDO', 'NOMBRE'],
            $sisCodes
        );

        return $this->post(
            "/api/examenes/{$examId}/habilitados/preview",
            ['archivo' => $this->csvFile($rows)],
            ['Accept' => 'application/json']
        );
    }

    private function examWithOwnRoster(int $count): Exam
    {
        $exam = $this->createExamWithRoster($count);

        return $exam;
    }

    private function createExamWithRoster(int $count = 1): Exam
    {
        $exam = $this->createExam();
        $this->seedRoster($exam, $count);

        return $exam;
    }

    private function seedRoster(Exam $exam, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $sis = sprintf('%09d', 300000001 + $i);

            $studentId = DB::table('estudiante')->insertGetId([
                'cod_sis' => $sis,
                'nombre' => "Estudiante {$i}",
                'apellido_paterno' => 'Apellido',
                'estado' => 'ACTIVO',
            ], 'id_estudiante');

            DB::table('grupo_examen')->insertOrIgnore([
                'id_examen' => $exam->id_examen,
                'id_grupo' => $this->grupoPropioId,
            ]);

            DB::table('grupo_estudiante')->insertOrIgnore([
                'id_grupo' => $this->grupoPropioId,
                'id_estudiante' => $studentId,
                'fecha_inscripcion' => '2026-02-15',
                'estado' => 'ACTIVO',
            ]);

            DB::table('examen_estudiante')->insertOrIgnore([
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $studentId,
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => 'NO_INGRESO',
            ]);
        }
    }
}
