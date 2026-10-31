<?php

namespace Tests\Feature\Exams;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * GET /api/examenes/{exam} — detalle para editar el examen o gestionar sus grupos.
 */
class ShowExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_muestra_el_detalle_con_grupos_y_ambientes_del_propio_examen(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $this->getJson("/api/examenes/{$exam->id_examen}")
            ->assertOk()
            ->assertJsonPath('data.id_examen', $exam->id_examen)
            ->assertJsonPath('data.ambientes.0.id_ambiente', $this->aulaId)
            ->assertJsonPath('data.grupos.0.id_grupo', $this->grupoPropioId);
    }

    public function test_rechaza_el_examen_de_otro_docente(): void
    {
        $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson("/api/examenes/{$exam->id_examen}")->assertForbidden();
    }

    public function test_responde_404_si_el_examen_no_existe(): void
    {
        $this->getJson('/api/examenes/999999')->assertNotFound();
    }
}
