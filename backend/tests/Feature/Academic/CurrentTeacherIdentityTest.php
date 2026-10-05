<?php

namespace Tests\Feature\Academic;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * RNF-02: el docente que actúa es la cuenta autenticada, nunca uno fijo de configuración.
 * Dos docentes en la misma base ven y crean solo lo suyo.
 */
class CurrentTeacherIdentityTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    public function test_cada_docente_lista_solo_sus_grupos(): void
    {
        $this->actAsTeacher($this->docenteId);
        $own = collect($this->getJson('/api/docente/grupos')->assertOk()->json('data'))->pluck('id_grupo');

        $this->assertTrue($own->contains($this->grupoPropioId));
        $this->assertFalse($own->contains($this->grupoAjenoId));

        $this->actAsTeacher($this->otroDocenteId);
        $other = collect($this->getJson('/api/docente/grupos')->assertOk()->json('data'))->pluck('id_grupo');

        $this->assertTrue($other->contains($this->grupoAjenoId));
        $this->assertFalse($other->contains($this->grupoPropioId));
    }

    public function test_el_grupo_que_crea_un_docente_le_pertenece_aunque_envie_otro_id(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'GOTRO',
            'id_usuario_docente' => $this->docenteId,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.grupo.es_mio', true);

        $this->assertSame(
            $this->otroDocenteId,
            DB::table('grupo')->where('num_grupo', 'GOTRO')->value('id_usuario_docente')
        );
    }

    public function test_un_docente_no_ve_el_detalle_del_grupo_de_otro(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson("/api/grupos/{$this->grupoPropioId}")->assertForbidden();
    }
}
