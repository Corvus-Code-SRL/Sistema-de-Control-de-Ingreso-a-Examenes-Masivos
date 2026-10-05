<?php

namespace Tests\Feature\Academic;

use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterAnalyzer;
use App\Services\Academic\Importers\StudentRosterConfirmer;
use App\Services\Academic\Importers\StudentRosterDatabaseMatch;
use App\Services\Academic\Importers\StudentRosterDatabaseMatcher;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use App\Services\Academic\Importers\StudentRosterStudentCreator;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-21: la confirmación inserta por lotes. Cuesta un número fijo de consultas, no una por
 * estudiante, y repetirla o cruzarla con otra carga del mismo estudiante no falla ni duplica.
 */
class StudentRosterBatchConfirmTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use BuildsRosterFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    /**
     * @return array{0: \Illuminate\Testing\TestResponse, 1: int, 2: array<int, string>}
     */
    private function confirmCountingQueries(string $token): array
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $response = $this->confirmRoster($token, $this->grupoPropioId);

        return [$response, count($statements), $statements];
    }

    private function previewToken(array $rows): string
    {
        $response = $this->previewRoster($this->csvFile($rows), $this->grupoPropioId);
        $response->assertOk();

        return (string) $response->json('data.token');
    }

    private function enrolled(): int
    {
        return DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)->count();
    }

    /* --------------------------- consultas por confirmación --------------------------- */

    public function test_confirmar_50_filas_nuevas_cuesta_10_consultas_o_menos(): void
    {
        $token = $this->previewToken($this->rosterRows(50));

        [$response, $queries, $statements] = $this->confirmCountingQueries($token);

        $response->assertOk();
        $response->assertJsonPath('data.estudiantes_creados', 50);
        $response->assertJsonPath('data.estudiantes_inscritos', 50);
        $this->assertLessThanOrEqual(10, $queries, "Se esperaban 10 consultas o menos:\n" . implode("\n", $statements));
        $this->assertSame(50, $this->enrolled());
        $this->assertSame(2, count(array_filter($statements, function (string $sql): bool {
            return strpos($sql, 'insert into') === 0;
        })), 'un insert de estudiantes y uno de inscripciones');
    }

    public function test_confirmar_1000_filas_nuevas_cuesta_12_consultas_o_menos(): void
    {
        $token = $this->previewToken($this->rosterRows(1000));

        [$response, $queries, $statements] = $this->confirmCountingQueries($token);

        $response->assertOk();
        $response->assertJsonPath('data.estudiantes_creados', 1000);
        $this->assertLessThanOrEqual(12, $queries, "Se esperaban 12 consultas o menos:\n" . implode("\n", $statements));
        $this->assertSame(1000, $this->enrolled());
    }

    public function test_confirmar_50_estudiantes_ya_existentes_tambien_es_por_lote(): void
    {
        $records = [];

        foreach ($this->rosterRows(50) as $row) {
            $records[] = [
                'cod_sis' => $row[0], 'ci' => null, 'nombre' => 'PREVIO', 'apellido_paterno' => 'EXISTENTE',
                'estado' => RecordStatus::ACTIVE,
            ];
        }

        DB::table('estudiante')->insert($records);
        $token = $this->previewToken($this->rosterRows(50));

        [$response, $queries] = $this->confirmCountingQueries($token);

        $response->assertOk();
        $response->assertJsonPath('data.estudiantes_creados', 0);
        $response->assertJsonPath('data.estudiantes_inscritos', 50);
        $this->assertLessThanOrEqual(10, $queries);
        $this->assertSame(50, $this->enrolled());
    }

    public function test_la_confirmacion_bloquea_la_fila_del_grupo(): void
    {
        $token = $this->previewToken($this->rosterRows(2));

        [, , $statements] = $this->confirmCountingQueries($token);

        $locks = array_filter($statements, function (string $sql): bool {
            return strpos($sql, 'from "grupo"') !== false && strpos($sql, 'for update') !== false;
        });

        $this->assertCount(1, $locks);
    }

    /* ----------------------------- idempotencia y reuso ----------------------------- */

    public function test_confirmar_dos_veces_el_mismo_token_no_duplica_ni_falla_con_500(): void
    {
        $token = $this->previewToken($this->rosterRows(5));

        $this->confirmRoster($token, $this->grupoPropioId)->assertOk();
        $this->confirmRoster($token, $this->grupoPropioId)->assertNotFound();

        $this->assertSame(5, $this->enrolled());
        $this->assertSame(5, DB::table('estudiante')->whereBetween('cod_sis', ['300000001', '300000005'])->count());
    }

    public function test_cargar_otra_vez_la_misma_nomina_es_idempotente(): void
    {
        $this->confirmRoster($this->previewToken($this->rosterRows(5)), $this->grupoPropioId)->assertOk();

        $second = $this->confirmRoster($this->previewToken($this->rosterRows(5)), $this->grupoPropioId);

        $second->assertOk();
        $second->assertJsonPath('data.estudiantes_creados', 0);
        $second->assertJsonPath('data.estudiantes_inscritos', 0);
        $second->assertJsonPath('data.ya_inscritos', 5);
        $this->assertSame(5, $this->enrolled());
    }

    public function test_un_estudiante_con_el_mismo_cod_sis_creado_despues_del_preview_se_reutiliza(): void
    {
        $token = $this->previewToken($this->rosterRows(3));

        // Otro docente lo importó entre el preview y la confirmación.
        $existingId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '300000002', 'ci' => '7654321', 'nombre' => 'OTRO DOCENTE',
            'apellido_paterno' => 'IMPORTO', 'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');

        $response = $this->confirmRoster($token, $this->grupoPropioId);

        $response->assertOk();
        $response->assertJsonPath('data.estudiantes_creados', 2);
        $response->assertJsonPath('data.estudiantes_inscritos', 3);
        $this->assertSame(1, DB::table('estudiante')->where('cod_sis', '300000002')->count());
        $this->assertSame('OTRO DOCENTE', DB::table('estudiante')->where('cod_sis', '300000002')->value('nombre'));
        $this->assertTrue(
            DB::table('grupo_estudiante')
                ->where('id_grupo', $this->grupoPropioId)
                ->where('id_estudiante', $existingId)
                ->exists()
        );
    }

    public function test_una_inscripcion_que_otra_confirmacion_hizo_antes_cuenta_como_ya_inscrita(): void
    {
        $studentId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '300000001', 'ci' => null, 'nombre' => 'ANA', 'apellido_paterno' => 'PEREZ',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPropioId, 'id_estudiante' => $studentId,
            'fecha_inscripcion' => '2026-02-15', 'estado' => RecordStatus::ACTIVE,
        ]);

        // El matcher llega tarde: clasifica como "existente" a quien ya quedó inscrito.
        $staleMatcher = new class extends StudentRosterDatabaseMatcher {
            public $studentId;

            public function classify(int $groupId, StudentRosterAnalysisResult $analysis): array
            {
                return [new StudentRosterDatabaseMatch(
                    $analysis->rows()[0],
                    $this->studentId,
                    StudentRosterDatabaseMatch::EXISTING_STUDENT
                )];
            }
        };
        $staleMatcher->studentId = $studentId;

        $analysis = (new StudentRosterAnalyzer(new StudentRosterRowValidator(8, 12)))->analyze([
            new StudentRosterRow(2, '300000001', 'PEREZ', 'ANA'),
        ]);

        $result = (new StudentRosterConfirmer(
            $staleMatcher,
            new StudentRosterStudentCreator(new StudentRosterStudentMapper())
        ))->confirm($this->grupoPropioId, $analysis);

        $this->assertSame(0, $result->enrolledStudents());
        $this->assertSame(1, $result->alreadyEnrolled());
        $this->assertSame(1, $this->enrolled());
    }
}
