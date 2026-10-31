<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Un examen FINALIZADO conserva la cantidad de estudiantes por grupo que tenía al cerrarse,
 * en el listado, en el detalle y en la respuesta de finalizar; el resto muestra el conteo vivo.
 */
class ExamFrozenStudentCountTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    private function examInGroup(string $state, string $startTime = '08:00'): Exam
    {
        $exam = $this->createExam(
            ['estado' => $state, 'hora_inicio' => $startTime],
            [$this->aulaId]
        );

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        return $exam;
    }

    /** @return array<int, int> cantidad_estudiantes por id_grupo de una respuesta de examen */
    private function countsByGroup(array $exam): array
    {
        $counts = [];

        foreach ($exam['grupos'] as $group) {
            $counts[(int) $group['id_grupo']] = (int) $group['cantidad_estudiantes'];
        }

        return $counts;
    }

    /** @return array<string, mixed> el examen dentro del listado GET /api/examenes */
    private function listed(Exam $exam): array
    {
        foreach ($this->getJson('/api/examenes')->assertOk()->json('data') as $item) {
            if ((int) $item['id_examen'] === (int) $exam->id_examen) {
                return $item;
            }
        }

        $this->fail("El examen {$exam->id_examen} no aparece en el listado.");
    }

    public function test_finalizado_conserva_su_conteo_en_listado_y_detalle_y_programado_muestra_el_vivo(): void
    {
        $finalized = $this->examInGroup(Exam::EN_INGRESO);
        $scheduled = $this->examInGroup(Exam::PROGRAMADO, '14:00');

        $this->postJson("/api/examenes/{$finalized->id_examen}/finalizar")->assertOk();

        $this->enrollStudents($this->grupoPropioId, 5);

        $this->assertSame([$this->grupoPropioId => 2], $this->countsByGroup($this->listed($finalized)));
        $this->assertSame([$this->grupoPropioId => 7], $this->countsByGroup($this->listed($scheduled)));

        $this->getJson("/api/examenes/{$finalized->id_examen}")
            ->assertOk()
            ->assertJsonPath('data.grupos.0.cantidad_estudiantes', 2);
        $this->getJson("/api/examenes/{$scheduled->id_examen}")
            ->assertOk()
            ->assertJsonPath('data.grupos.0.cantidad_estudiantes', 7);
    }

    public function test_respuesta_de_finalizar_coincide_con_el_conteo_congelado_del_detalle(): void
    {
        $exam = $this->examInGroup(Exam::EN_INGRESO);

        // Un estudiante inscrito en dos grupos del examen se congela una sola vez.
        $sharedStudentId = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->value('id_estudiante');
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoAjenoId,
            'id_estudiante' => $sharedStudentId,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => 'ACTIVO',
        ]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoAjenoId,
        ]);

        $finish = $this->postJson("/api/examenes/{$exam->id_examen}/finalizar")->assertOk();
        $detail = $this->getJson("/api/examenes/{$exam->id_examen}")->assertOk();

        $finishCounts = $this->countsByGroup($finish->json('data'));

        $this->assertSame($this->countsByGroup($detail->json('data')), $finishCounts);
        $this->assertSame(2, array_sum($finishCounts));
    }

    public function test_el_conteo_congelado_usa_una_sola_consulta_sobre_examen_estudiante(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $exam = $this->examInGroup(Exam::EN_INGRESO, sprintf('%02d:00', 8 + $i));
            $this->postJson("/api/examenes/{$exam->id_examen}/finalizar")->assertOk();
        }

        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (strpos($query->sql, 'examen_estudiante') !== false) {
                $queries++;
            }
        });

        $this->getJson('/api/examenes')->assertOk();

        $this->assertSame(1, $queries);
    }

    public function test_sin_finalizados_el_listado_no_consulta_examen_estudiante(): void
    {
        $this->examInGroup(Exam::PROGRAMADO);

        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (strpos($query->sql, 'examen_estudiante') !== false) {
                $queries++;
            }
        });

        $this->getJson('/api/examenes')->assertOk();

        $this->assertSame(0, $queries);
    }
}
