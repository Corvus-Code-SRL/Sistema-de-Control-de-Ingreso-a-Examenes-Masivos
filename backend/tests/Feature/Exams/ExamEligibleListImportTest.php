<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Exams\Importers\EligibleListPreviewResult;
use App\Support\MotivoNoHabilitacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamEligibleListImportTest extends TestCase
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

    public function test_confirm_devuelve_cambios_pendientes_y_no_aplica(): void
    {
        // Los 2 estudiantes del seed; sin seedRoster para no duplicar.
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentIds = $this->studentIdsInGroup();
        $this->assertCount(2, $studentIds);

        $sisCodes = DB::table('estudiante')
            ->whereIn('id_estudiante', $studentIds)
            ->orderBy('id_estudiante')
            ->pluck('cod_sis')
            ->all();

        // El primer estudiante está en el archivo y estaba NO_HABILITADO:
        // confirm() propondrá HABILITADO. El segundo no está en el archivo
        // y está sin fila (HABILITADO por defecto): confirm() propondrá
        // NO_HABILITADO. Total: 2 cambios.
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentIds[0],
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => 'ASISTENCIA_INSUFICIENTE',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        $before = DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_estudiante')
            ->get(['id_estudiante', 'estado_habilitacion', 'observacion'])
            ->map(static fn ($row): array => (array) $row)
            ->all();

        $token = $this->previewCsv($exam, [$sisCodes[0]])
            ->assertOk()
            ->json('data.token');

        $response = $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertOk();

        $response
            ->assertJsonPath('data.total_filas', 1)
            ->assertJsonPath('data.filas_inconsistentes', 0)
            ->assertJsonPath('data.no_encontrados', 0)
            ->assertJsonCount(2, 'data.cambios_pendientes')
            ->assertJsonPath('data.cambios_pendientes.0.codigo_sis', $sisCodes[0])
            ->assertJsonPath('data.cambios_pendientes.0.estado_habilitacion', 'HABILITADO')
            ->assertJsonPath('data.cambios_pendientes.0.motivo', null)
            ->assertJsonPath('data.cambios_pendientes.0.detalle', null)
            ->assertJsonPath('data.cambios_pendientes.1.codigo_sis', $sisCodes[1])
            ->assertJsonPath('data.cambios_pendientes.1.estado_habilitacion', 'NO_HABILITADO')
            ->assertJsonPath('data.cambios_pendientes.1.motivo', MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE)
            ->assertJsonPath('data.cambios_pendientes.1.detalle', null);

        // El confirm NO aplica: examen_estudiante queda exactamente igual.
        $after = DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_estudiante')
            ->get(['id_estudiante', 'estado_habilitacion', 'observacion'])
            ->map(static fn ($row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after, 'El confirm no debe escribir en examen_estudiante.');
    }

    public function test_confirm_token_invalido(): void
    {
        $exam = $this->createExamWithRoster(1);

        $before = DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_estudiante')
            ->get(['id_estudiante', 'estado_habilitacion', 'observacion'])
            ->map(static fn ($row): array => (array) $row)
            ->all();

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => 'no-es-un-token',
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_confirm_token_inexistente(): void
    {
        $exam = $this->createExamWithRoster(1);

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => str_repeat('a', 64),
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertNotFound();
    }

    public function test_confirm_token_de_otro_docente(): void
    {
        $exam = $this->createExamWithRoster(1);

        $token = $this->previewCsv($exam, ['300000001'])->json('data.token');

        $this->actAsTeacher($this->otroDocenteId);

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertForbidden();
    }

    public function test_confirm_token_de_otro_examen(): void
    {
        $exam = $this->createExamWithRoster(1);
        $other = $this->createExam();

        $token = $this->previewCsv($exam, ['300000001'])->json('data.token');

        $this->postJson("/api/examenes/{$other->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertStatus(422);
    }

    public function test_confirm_con_motivo_otro_exige_detalle(): void
    {
        $exam = $this->createExamWithRoster(1);

        $token = $this->previewCsv($exam, ['300000001'])->json('data.token');

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::OTRO,
        ])->assertStatus(422)->assertJsonValidationErrors(['detalle']);
    }

    public function test_confirm_propaga_motivo_a_los_no_habilitados(): void
    {
        $exam = $this->createExamWithRoster(2);

        $token = $this->previewCsv($exam, ['300000001'])
            ->assertOk()
            ->json('data.token');

        $cambios = $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertOk()->json('data.cambios_pendientes');

        $noHabilitados = array_values(array_filter(
            $cambios,
            static fn (array $c): bool => $c['estado_habilitacion'] === 'NO_HABILITADO'
        ));

        $this->assertNotEmpty($noHabilitados);

        foreach ($noHabilitados as $cambio) {
            $this->assertSame('NO_HABILITADO', $cambio['estado_habilitacion']);
            $this->assertSame(MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE, $cambio['motivo']);
            $this->assertNull($cambio['detalle']);
        }
    }

    public function test_confirm_con_motivo_otro_propaga_detalle(): void
    {
        $exam = $this->createExamWithRoster(2);

        $token = $this->previewCsv($exam, ['300000001'])
            ->assertOk()
            ->json('data.token');

        $cambios = $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::OTRO,
            'detalle' => 'Documento pendiente de firma',
        ])->assertOk()->json('data.cambios_pendientes');

        $noHabilitados = array_values(array_filter(
            $cambios,
            static fn (array $c): bool => $c['estado_habilitacion'] === 'NO_HABILITADO'
        ));

        $this->assertNotEmpty($noHabilitados);

        foreach ($noHabilitados as $cambio) {
            $this->assertSame('NO_HABILITADO', $cambio['estado_habilitacion']);
            $this->assertSame(MotivoNoHabilitacion::OTRO, $cambio['motivo']);
            $this->assertSame('Documento pendiente de firma', $cambio['detalle']);
        }
    }

    public function test_confirm_examen_en_ingreso_devuelve_409(): void
    {
        $exam = $this->createExamWithRoster(1);

        $token = $this->previewCsv($exam, ['300000001'])->json('data.token');

        DB::table('examen')->where('id_examen', $exam->id_examen)->update(['estado' => Exam::EN_INGRESO]);

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertStatus(409);
    }

    public function test_count_missing_incluye_estudiantes_sin_fila(): void
    {
        // Los 2 estudiantes del seed; sin seedRoster para no duplicar.
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentIds = $this->studentIdsInGroup();
        $this->assertCount(2, $studentIds);

        // Sin filas en examen_estudiante: la nómina vive en grupo_estudiante.
        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count()
        );

        $sisCodes = DB::table('estudiante')
            ->whereIn('id_estudiante', $studentIds)
            ->orderBy('id_estudiante')
            ->pluck('cod_sis')
            ->all();

        // El archivo solo trae al primer estudiante.
        $response = $this->previewCsv($exam, [$sisCodes[0]])->assertOk();

        // faltantes = 1 (el segundo, sin fila, cuenta como "de la nómina no figura").
        $response->assertJsonPath('data.faltantes', 1);
        $response->assertJsonPath('data.filas.0.estado', EligibleListPreviewResult::MATCHED);
    }

    public function test_confirm_propaga_motivo_a_estudiantes_sin_fila(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentIds = $this->studentIdsInGroup();
        $this->assertCount(2, $studentIds);

        DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->delete();

        $sisCodes = DB::table('estudiante')
            ->whereIn('id_estudiante', $studentIds)
            ->orderBy('id_estudiante')
            ->pluck('cod_sis')
            ->all();

        $token = $this->previewCsv($exam, [$sisCodes[0]])->assertOk()->json('data.token');

        $cambios = $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => $token,
            'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
        ])->assertOk()->json('data.cambios_pendientes');

        $noHabilitados = array_values(array_filter(
            $cambios,
            static fn (array $c): bool => $c['estado_habilitacion'] === 'NO_HABILITADO'
        ));

        $this->assertCount(1, $noHabilitados);
        $this->assertSame(MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE, $noHabilitados[0]['motivo']);
    }

    /**
     * @param array<int, string> $sisCodes
     */
    private function previewCsv(Exam $exam, array $sisCodes)
    {
        $rows = array_map(
            static fn (string $sis): array => [$sis, 'APELLIDO', 'NOMBRE'],
            $sisCodes
        );

        return $this->post(
            "/api/examenes/{$exam->id_examen}/habilitados/preview",
            ['archivo' => $this->csvFile($rows)],
            ['Accept' => 'application/json']
        );
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

    /**
     * Estudiantes inscritos en el grupo propio según grupo_estudiante.
     *
     * @return array<int, int>
     */
    private function studentIdsInGroup(): array
    {
        return DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
