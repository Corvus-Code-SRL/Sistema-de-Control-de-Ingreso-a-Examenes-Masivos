<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Support\MotivoNoHabilitacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamEligibleListCurrentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
        $this->actAsTeacher($this->docenteId);
    }

    public function test_devuelve_lista_con_estado_por_estudiante(): void
    {
        // seedExamCatalog ya inscribe 2 estudiantes en grupoPropioId.
        // Sumamos 1 más para tener 3 y poder probar los tres estados.
        $this->enrollStudents($this->grupoPropioId, 1);

        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentIds = $this->studentIdsInGroup();
        $this->assertCount(3, $studentIds, 'El escenario debe tener exactamente 3 estudiantes en la nómina.');

        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentIds[0],
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => 'ASISTENCIA_INSUFICIENTE',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        $response = $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->assertJsonPath('data.editable', true)
            ->assertJsonCount(3, 'data.estudiantes')
            ->assertJsonPath('data.estado', Exam::PROGRAMADO);

        $byId = collect($response->json('data.estudiantes'))->keyBy('id_estudiante');

        $this->assertSame('NO_HABILITADO', $byId[$studentIds[0]]['estado_habilitacion']);
        $this->assertSame('ASISTENCIA_INSUFICIENTE', $byId[$studentIds[0]]['motivo']);
        $this->assertSame('HABILITADO', $byId[$studentIds[1]]['estado_habilitacion']);
        $this->assertFalse($byId[$studentIds[1]]['caso_especial']);
        $this->assertSame('HABILITADO', $byId[$studentIds[2]]['estado_habilitacion']);
    }

    public function test_editable_true_en_programado(): void
    {
        $exam = $this->createExamWithRoster(1);

        $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->assertJsonPath('data.editable', true);
    }

    public function test_editable_false_en_en_curso(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->seedRoster($exam, 1);

        $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.estado', Exam::EN_CURSO);
    }

    public function test_motivo_y_detalle_se_deserializan_de_observacion(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        // La fila no existe: la creamos con el estado y la observación deseados.
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => MotivoNoHabilitacion::toObservation(
                MotivoNoHabilitacion::OTRO,
                'Documento pendiente'
            ),
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        $estudiante = collect($this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->json('data.estudiantes'))
            ->firstWhere('id_estudiante', $studentId);

        $this->assertNotNull($estudiante);
        $this->assertSame('OTRO', $estudiante['motivo']);
        $this->assertSame('Documento pendiente', $estudiante['detalle']);
    }

    public function test_examen_de_otro_docente_devuelve_403(): void
    {
        $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertForbidden();
    }

    public function test_examen_inexistente_devuelve_404(): void
    {
        $this->getJson('/api/examenes/999999/habilitados')->assertNotFound();
    }

    public function test_caso_especial_se_marca_con_flag(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        // La fila no existe: la creamos con el estado CASO ESPECIAL.
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'CASO ESPECIAL',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        $estudiante = collect($this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->json('data.estudiantes'))
            ->firstWhere('id_estudiante', $studentId);

        $this->assertNotNull($estudiante);
        $this->assertTrue($estudiante['caso_especial']);
        $this->assertSame('CASO ESPECIAL', $estudiante['estado_habilitacion']);
    }

    public function test_estudiante_sin_fila_aparece_como_habilitado(): void
    {
        // Los 2 estudiantes del seed; sin seedRoster para no duplicar.
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count(),
            'El escenario debe empezar sin filas en examen_estudiante.'
        );

        $response = $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->assertJsonCount(2, 'data.estudiantes');

        foreach ($response->json('data.estudiantes') as $estudiante) {
            $this->assertSame('HABILITADO', $estudiante['estado_habilitacion']);
            $this->assertNull($estudiante['motivo']);
            $this->assertNull($estudiante['detalle']);
            $this->assertFalse($estudiante['caso_especial']);
        }
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
