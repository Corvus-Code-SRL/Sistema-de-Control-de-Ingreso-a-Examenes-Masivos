<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Policies\Academic\GroupPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-21: quién puede cargar la nómina de un grupo. Solo el docente que lo dicta; un Auxiliar
 * nunca; un grupo inexistente es un 404 limpio; los ids se validan antes de llegar a la base.
 */
class StudentRosterAuthorizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use BuildsRosterFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    /** @return array{0: int, 1: int} estudiantes e inscripciones que hay ahora */
    private function writes(): array
    {
        return [DB::table('estudiante')->count(), DB::table('grupo_estudiante')->count()];
    }

    public function test_el_docente_del_grupo_puede_previsualizar_y_confirmar(): void
    {
        $preview = $this->previewRoster($this->csvFile($this->rosterRows(2)), $this->grupoPropioId);

        $preview->assertOk();
        $this->confirmRoster($preview->json('data.token'), $this->grupoPropioId)->assertOk();
    }

    /* ------------------------------- Auxiliar -------------------------------- */

    public function test_un_auxiliar_recibe_403_al_previsualizar_y_no_se_escribe_nada(): void
    {
        $this->actAsUserId($this->createAssistant('Beto', '202400010'));
        $before = $this->writes();

        $this->previewRoster($this->csvFile($this->rosterRows(2)), $this->grupoPropioId)
            ->assertForbidden()
            ->assertJsonPath('message', 'Un auxiliar no puede cargar la nómina de un grupo.');

        $this->assertSame($before, $this->writes());
    }

    public function test_un_auxiliar_recibe_403_al_confirmar_aunque_tenga_un_token_valido(): void
    {
        $preview = $this->previewRoster($this->csvFile($this->rosterRows(2)), $this->grupoPropioId);
        $token = $preview->json('data.token');
        $this->actAsUserId($this->createAssistant('Beto', '202400010'));
        $before = $this->writes();

        $this->confirmRoster($token, $this->grupoPropioId)
            ->assertForbidden()
            ->assertJsonPath('message', 'Un auxiliar no puede cargar la nómina de un grupo.');

        $this->assertSame($before, $this->writes());
        $this->assertSame(0, DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)->count());
    }

    /* --------------------------- propiedad del grupo --------------------------- */

    public function test_otro_docente_recibe_403_de_la_policy_al_previsualizar(): void
    {
        $this->actAsTeacher($this->otroDocenteId);
        $before = $this->writes();

        $this->previewRoster($this->csvFile($this->rosterRows(2)), $this->grupoPropioId)
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente que dicta el grupo puede cargar su nómina.');

        $this->assertSame($before, $this->writes());
    }

    public function test_otro_docente_recibe_403_de_la_policy_al_confirmar(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        $this->confirmRoster(str_repeat('a', 64), $this->grupoPropioId)
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente que dicta el grupo puede cargar su nómina.');
    }

    public function test_la_policy_reutiliza_la_comprobacion_de_dueno_de_view(): void
    {
        $policy = new GroupPolicy();
        $group = Group::query()->findOrFail($this->grupoPropioId);

        $this->assertTrue($policy->manageRoster(null, $group, $this->docenteId)->allowed());
        $this->assertFalse($policy->manageRoster(null, $group, $this->otroDocenteId)->allowed());
        $this->assertSame(
            $policy->view(null, $group, $this->otroDocenteId)->allowed(),
            $policy->manageRoster(null, $group, $this->otroDocenteId)->allowed()
        );
    }

    /* ---------------------------- grupo inexistente ---------------------------- */

    public function test_un_grupo_inexistente_responde_404_sin_el_nombre_del_modelo(): void
    {
        foreach ([
            $this->previewRoster($this->csvFile($this->rosterRows(1)), 999999999),
            $this->confirmRoster(str_repeat('a', 64), 999999999),
        ] as $response) {
            $response->assertNotFound();
            $response->assertJsonPath('message', 'No existe el grupo indicado.');
            $this->assertStringNotContainsString('App\\Models', (string) $response->getContent());
        }
    }

    /* ------------------------------ ids inválidos ------------------------------ */

    /**
     * @dataProvider invalidGroupIds
     */
    public function test_rechaza_un_id_de_grupo_invalido_al_previsualizar(string $id): void
    {
        $this->previewRoster($this->csvFile($this->rosterRows(1)), $id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');
    }

    /**
     * @dataProvider invalidGroupIds
     */
    public function test_rechaza_un_id_de_grupo_invalido_al_confirmar(string $id): void
    {
        $this->confirmRoster(str_repeat('a', 64), $id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');
    }

    /** @return array<string, array{0: string}> */
    public function invalidGroupIds(): array
    {
        return [
            'no numérico' => ['abc'],
            'cero' => ['0'],
            'negativo' => ['-1'],
            'decimal' => ['1.5'],
            'sobre el integer' => ['2147483648'],
            'enorme' => ['99999999999'],
        ];
    }

    /* ---------------------------- período sin configurar ---------------------------- */

    public function test_sin_periodo_activo_configurado_responde_el_error_de_configuracion(): void
    {
        config()->set('sciem.periodo_activo_id', null);

        $response = $this->previewRoster($this->csvFile($this->rosterRows(1)), $this->grupoPropioId);

        $response->assertStatus(500);
        $this->assertStringContainsString('SCIEM_PERIODO_ACTIVO_ID', (string) $response->json('message'));
        $this->assertNull($response->json('exception'));
    }
}
