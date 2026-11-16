<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Support\MotivoNoHabilitacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamEligibleListSaveTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
        $this->actAsTeacher($this->docenteId);
    }

    public function test_guarda_un_cambio_habilitado_a_no_habilitado(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => 'ASISTENCIA_INSUFICIENTE',
        ]);
    }

    public function test_guarda_un_cambio_no_habilitado_a_habilitado(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->update(['estado_habilitacion' => 'NO_HABILITADO', 'observacion' => 'ASISTENCIA_INSUFICIENTE']);

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_habilitacion' => 'HABILITADO',
        ]);
    }

    public function test_save_no_modifica_caso_especial_aunque_no_se_toque(): void
    {
        $exam = $this->createExamWithRoster(2);
        [$casoEspecialId, $otroId] = $this->studentIds($exam);

        DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $casoEspecialId)
            ->update(['estado_habilitacion' => 'CASO ESPECIAL']);

        // Cambio válido sobre el otro estudiante. El CASO ESPECIAL no se toca.
        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $otroId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $casoEspecialId,
            'estado_habilitacion' => 'CASO ESPECIAL',
        ]);
    }

    public function test_save_no_permite_tocar_caso_especial(): void
    {
        $exam = $this->createExamWithRoster(1);
        $casoEspecialId = $this->studentIds($exam)[0];

        DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $casoEspecialId)
            ->update(['estado_habilitacion' => 'CASO ESPECIAL']);

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $casoEspecialId,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['cambios']);

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $casoEspecialId,
            'estado_habilitacion' => 'CASO ESPECIAL',
        ]);
    }
    
    public function test_resetea_no_habilitado_a_habilitado(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->update(['estado_habilitacion' => 'NO_HABILITADO', 'observacion' => 'ASISTENCIA_INSUFICIENTE']);

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_habilitacion' => 'HABILITADO',
            'observacion' => 'ASISTENCIA_INSUFICIENTE', // no se limpia
        ]);
    }

    public function test_marca_no_habilitado_a_los_faltantes(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => MotivoNoHabilitacion::NO_ENTREGO_TRABAJO_FINAL,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => 'NO_ENTREGO_TRABAJO_FINAL',
        ]);
    }

    public function test_id_estudiante_ajeno_devuelve_422(): void
    {
        $exam = $this->createExamWithRoster(1);

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => 999999,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['cambios']);
    }

    public function test_caso_especial_devuelve_422(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->update(['estado_habilitacion' => 'CASO ESPECIAL']);

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['cambios']);
    }

    public function test_no_habilitado_sin_motivo_devuelve_422(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'NO_HABILITADO',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['cambios.0.motivo']);
    }

    public function test_otro_sin_detalle_devuelve_422(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => MotivoNoHabilitacion::OTRO,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['cambios.0.detalle']);
    }

    public function test_habilitado_no_exige_motivo(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertOk();
    }

    public function test_sin_cambios_devuelve_422(): void
    {
        $exam = $this->createExamWithRoster(1);

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['cambios']);
    }

    public function test_id_estudiante_repetido_devuelve_422(): void
    {
        $exam = $this->createExamWithRoster(1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [
                ['id_estudiante' => $studentId, 'estado_habilitacion' => 'HABILITADO'],
                ['id_estudiante' => $studentId, 'estado_habilitacion' => 'HABILITADO'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['cambios.1.id_estudiante']);
    }

    public function test_examen_en_ingreso_devuelve_409(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);
        $this->seedRoster($exam, 1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
            ]],
        ])->assertStatus(409);
    }

    public function test_examen_de_otro_docente_devuelve_403(): void
    {
        $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);
        $this->seedRoster($exam, 1);
        $studentId = $this->studentIds($exam)[0];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertForbidden();
    }

    public function test_examen_inexistente_devuelve_404(): void
    {
        $this->putJson('/api/examenes/999999/habilitados', [
            'cambios' => [[
                'id_estudiante' => 1,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertNotFound();
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
     * @return array<int, int>
     */
    private function studentIds(Exam $exam): array
    {
        return DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

        public function test_guarda_cambio_sobre_estudiante_sin_fila_en_examen_estudiante(): void
    {
        // El seed ya inscribe 2 estudiantes en grupoPropioId. Creamos el examen
        // con el grupo asignado pero SIN poblar examen_estudiante, que es el
        // estado real de un examen recién creado con la Solución 3.
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count(),
            'El escenario debe empezar sin fila en examen_estudiante.'
        );

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }

    public function test_guarda_cambio_sobre_estudiante_sin_fila_y_vuelve_a_habilitarlo(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        // Primero lo deshabilitamos (crea la fila vía ensureRow).
        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => MotivoNoHabilitacion::NO_ENTREGO_TRABAJO_FINAL,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_habilitacion' => 'NO_HABILITADO',
        ]);

        // Ahora lo volvemos a habilitar: la fila ya existe, no debe duplicarse.
        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $studentId,
                'estado_habilitacion' => 'HABILITADO',
            ]],
        ])->assertOk();

        $this->assertSame(
            1,
            DB::table('examen_estudiante')
                ->where('id_examen', $exam->id_examen)
                ->where('id_estudiante', $studentId)
                ->count()
        );
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_habilitacion' => 'HABILITADO',
        ]);
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
