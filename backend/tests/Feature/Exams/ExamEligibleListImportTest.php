<?php

namespace Tests\Feature\Exams;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Services\Exams\ExamEligibleListAccess;
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

    public function test_confirm_reemplaza_la_lista_en_una_sola_peticion(): void
    {
        $exam = $this->examWithSeedRoster();
        [$first, $second] = $this->studentIdsInGroup();
        $sisCodes = $this->sisCodes([$first, $second]);

        // El primero estaba NO_HABILITADO y figura en el archivo; el segundo no tiene fila y no figura.
        $this->insertRow($exam, $first, 'NO_HABILITADO', 'ASISTENCIA_INSUFICIENTE');

        $token = $this->previewCsv($exam, [$sisCodes[0], '399999999'])->assertOk()->json('data.token');

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.total_filas', 2)
            ->assertJsonPath('data.filas_inconsistentes', 0)
            ->assertJsonPath('data.no_encontrados', 1)
            ->assertJsonPath('data.habilitados', 1)
            ->assertJsonPath('data.total_no_habilitados', 1)
            ->assertJsonPath('data.motivo', MotivoNoHabilitacion::NO_FIGURA_EN_LISTA)
            ->assertJsonCount(1, 'data.no_habilitados')
            ->assertJsonPath('data.no_habilitados.0.id_estudiante', $second)
            ->assertJsonPath('data.no_habilitados.0.codigo_sis', $sisCodes[1])
            ->assertJsonStructure(['data' => ['no_habilitados' => [['nombre_completo']]]]);

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $first,
            'estado_habilitacion' => 'HABILITADO',
            'observacion' => null,
        ]);
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $second,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => MotivoNoHabilitacion::NO_FIGURA_EN_LISTA,
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }

    public function test_confirm_sobrescribe_los_ajustes_manuales_anteriores(): void
    {
        $exam = $this->examWithSeedRoster();
        [$first, $second] = $this->studentIdsInGroup();
        $sisCodes = $this->sisCodes([$first, $second]);

        // Ajustes manuales previos: el primero (que figura en el archivo) no habilitado con un motivo
        // elegido a mano y el segundo (que no figura) no habilitado con otro motivo.
        $this->insertRow($exam, $first, 'NO_HABILITADO', 'OTRO: Documento pendiente');
        $this->insertRow($exam, $second, 'NO_HABILITADO', 'NO_CUMPLE_PRERREQUISITO');

        $token = $this->previewCsv($exam, [$sisCodes[0]])->assertOk()->json('data.token');

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.total_no_habilitados', 1);

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $first,
            'estado_habilitacion' => 'HABILITADO',
            'observacion' => null,
        ]);
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $second,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => MotivoNoHabilitacion::NO_FIGURA_EN_LISTA,
        ]);
    }

    public function test_confirm_no_pisa_estado_de_ingreso_hora_ni_ambiente_ni_toca_caso_especial(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        [$first, $second] = $this->studentIdsInGroup();

        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $first,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '08:05:00',
            'id_ambiente' => $this->aulaId,
        ]);
        $this->insertRow($exam, $second, 'CASO ESPECIAL', null);

        $token = $this->previewCsv($exam, ['399999999'])->assertOk()->json('data.token');

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.total_no_habilitados', 1)
            ->assertJsonPath('data.no_habilitados.0.id_estudiante', $first);

        $row = DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)->where('id_estudiante', $first)->first();
        $this->assertSame('NO_HABILITADO', $row->estado_habilitacion);
        $this->assertSame('INGRESO', $row->estado_ingreso);
        $this->assertSame('08:05:00', $row->hora_ingreso);
        $this->assertSame($this->aulaId, (int) $row->id_ambiente);

        $this->assertSame(
            'CASO ESPECIAL',
            DB::table('examen_estudiante')
                ->where('id_examen', $exam->id_examen)->where('id_estudiante', $second)
                ->value('estado_habilitacion')
        );
    }

    public function test_confirm_audita_una_sola_vez_con_origen_y_conteos(): void
    {
        $exam = $this->examWithSeedRoster();
        $sisCodes = $this->sisCodes($this->studentIdsInGroup());
        $logsBefore = AuditLog::query()->where('tabla_afectada', 'examen_estudiante')->count();

        $token = $this->previewCsv($exam, [$sisCodes[0]])->assertOk()->json('data.token');
        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])->assertOk();

        $logs = AuditLog::query()->where('tabla_afectada', 'examen_estudiante')->get();
        $this->assertCount($logsBefore + 1, $logs);

        $log = $logs->sortByDesc('id_log')->first();
        $this->assertSame('GUARDAR_HABILITADOS', $log->accion->operacion);
        $this->assertSame('ARCHIVO', $log->nuevo_valor['origen']);
        $this->assertSame(1, $log->nuevo_valor['habilitados']);
        $this->assertSame(1, $log->nuevo_valor['no_habilitados']);
        $this->assertSame($this->docenteId, $log->id_usuario);
    }

    public function test_un_segundo_confirm_con_el_mismo_token_se_rechaza(): void
    {
        $exam = $this->examWithSeedRoster();
        $sisCodes = $this->sisCodes($this->studentIdsInGroup());

        $token = $this->previewCsv($exam, [$sisCodes[0]])->assertOk()->json('data.token');

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])->assertOk();
        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertNotFound();
    }

    public function test_una_falla_a_mitad_de_la_escritura_no_deja_filas_cambiadas_ni_bitacora(): void
    {
        $exam = $this->examWithSeedRoster();
        [$first, $second] = $this->studentIdsInGroup();
        $this->insertRow($exam, $first, 'NO_HABILITADO', 'ASISTENCIA_INSUFICIENTE');

        $before = $this->rowsOf($exam);
        $logsBefore = AuditLog::query()->where('tabla_afectada', 'examen_estudiante')->count();

        $token = $this->previewCsv($exam, [$this->sisCodes([$first])[0]])->assertOk()->json('data.token');

        // El primer estudiante se escribe bien; al segundo la base de datos le rechaza la escritura.
        DB::unprepared("
            CREATE FUNCTION zz_fallo_lista_habilitados() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.id_estudiante = {$second} THEN
                    RAISE EXCEPTION 'fallo forzado a mitad de la escritura';
                END IF;
                RETURN NEW;
            END \$\$;
            CREATE TRIGGER zz_fallo_lista_habilitados BEFORE UPDATE ON examen_estudiante
                FOR EACH ROW EXECUTE FUNCTION zz_fallo_lista_habilitados();
        ");

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertStatus(500);

        $this->assertSame($before, $this->rowsOf($exam), 'No debe quedar ninguna fila cambiada.');
        $this->assertSame(
            $logsBefore,
            AuditLog::query()->where('tabla_afectada', 'examen_estudiante')->count(),
            'No debe quedar ninguna entrada de bitácora.'
        );

        // El token no se consumió: al quitar la falla, el mismo preview se puede confirmar.
        DB::unprepared('DROP TRIGGER zz_fallo_lista_habilitados ON examen_estudiante');
        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])->assertOk();
    }

    public function test_confirm_con_el_control_de_ingreso_abierto_devuelve_409_y_no_escribe(): void
    {
        $exam = $this->examWithSeedRoster();
        $sisCodes = $this->sisCodes($this->studentIdsInGroup());
        $token = $this->previewCsv($exam, [$sisCodes[0]])->assertOk()->json('data.token');

        $this->putExamInState($exam->id_examen, 'INGRESO_ABIERTO');
        $before = $this->rowsOf($exam);

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('message', ExamEligibleListAccess::WINDOW_CLOSED_MESSAGE);

        $this->assertSame($before, $this->rowsOf($exam));
    }

    public function test_confirm_examen_en_curso_devuelve_409(): void
    {
        $exam = $this->examWithSeedRoster();
        $sisCodes = $this->sisCodes($this->studentIdsInGroup());
        $token = $this->previewCsv($exam, [$sisCodes[0]])->json('data.token');

        DB::table('examen')->where('id_examen', $exam->id_examen)->update(['estado' => Exam::EN_CURSO]);

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertStatus(409);
    }

    public function test_confirm_token_invalido(): void
    {
        $exam = $this->examWithSeedRoster();

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => 'no-es-un-token',
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_confirm_token_inexistente(): void
    {
        $exam = $this->examWithSeedRoster();

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", [
            'token' => str_repeat('a', 64),
        ])->assertNotFound();
    }

    public function test_confirm_token_de_otro_docente(): void
    {
        $exam = $this->examWithSeedRoster();
        $sisCodes = $this->sisCodes($this->studentIdsInGroup());

        $token = $this->previewCsv($exam, [$sisCodes[0]])->json('data.token');

        $this->actAsTeacher($this->otroDocenteId);

        $this->postJson("/api/examenes/{$exam->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertForbidden();
    }

    public function test_confirm_token_de_otro_examen(): void
    {
        $exam = $this->examWithSeedRoster();
        $other = $this->createExam();
        $sisCodes = $this->sisCodes($this->studentIdsInGroup());

        $token = $this->previewCsv($exam, [$sisCodes[0]])->json('data.token');

        $this->postJson("/api/examenes/{$other->id_examen}/habilitados/confirm", ['token' => $token])
            ->assertStatus(422);
    }

    public function test_count_missing_incluye_estudiantes_sin_fila(): void
    {
        $exam = $this->examWithSeedRoster();
        $studentIds = $this->studentIdsInGroup();

        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count()
        );

        $sisCodes = $this->sisCodes($studentIds);

        $this->previewCsv($exam, [$sisCodes[0]])
            ->assertOk()
            ->assertJsonPath('data.faltantes', 1)
            ->assertJsonPath('data.filas.0.estado', EligibleListPreviewResult::MATCHED);
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

    /** Examen con el grupo propio asignado (sus 2 estudiantes del seed) y sin filas en examen_estudiante. */
    private function examWithSeedRoster(): Exam
    {
        $exam = $this->createExam();

        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        return $exam;
    }

    private function insertRow(Exam $exam, int $studentId, string $state, ?string $observation): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => $state,
            'observacion' => $observation,
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rowsOf(Exam $exam): array
    {
        return DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_estudiante')
            ->get(['id_estudiante', 'estado_habilitacion', 'observacion', 'estado_ingreso'])
            ->map(static fn ($row): array => (array) $row)
            ->all();
    }

    /**
     * @param array<int, int> $studentIds
     * @return array<int, string>
     */
    private function sisCodes(array $studentIds): array
    {
        return DB::table('estudiante')
            ->whereIn('id_estudiante', $studentIds)
            ->orderBy('id_estudiante')
            ->pluck('cod_sis')
            ->all();
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
