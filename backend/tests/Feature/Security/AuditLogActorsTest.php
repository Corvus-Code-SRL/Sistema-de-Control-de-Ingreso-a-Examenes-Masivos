<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use App\Support\SystemActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-015: quién figura como autor de cada operación (persona, «Automático» o «Sistema») y las etiquetas de la
 * lista y del detalle.
 */
class AuditLogActorsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    private User $docente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareAuditLog();

        $this->docente = $this->namedAccount('Marcelo', 'Quiroga', '10452', Role::DOCENTE);
    }

    public function test_una_persona_figura_con_su_nombre(): void
    {
        $this->logRow($this->docente, 'CREAR', 'examen', null, ['id_examen' => 1]);

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.usuario.tipo', 'persona')
            ->assertJsonPath('data.0.usuario.etiqueta', 'Marcelo Quiroga')
            ->assertJsonPath('data.0.automatico', false);
    }

    public function test_el_inicio_automatico_se_muestra_como_automatico_y_el_detalle_nombra_al_docente(): void
    {
        $id = $this->logRow(
            $this->docente,
            'INICIAR',
            'examen',
            ['id_examen' => 5, 'estado' => 'EN_INGRESO'],
            ['id_examen' => 5, 'estado' => 'EN_CURSO', 'tipo_inicio' => 'AUTOMATICO']
        );

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.usuario.tipo', 'automatico')
            ->assertJsonPath('data.0.usuario.etiqueta', 'Automático')
            ->assertJsonPath('data.0.automatico', true);

        $this->getJson("/api/bitacora/{$id}")
            ->assertOk()
            ->assertJsonPath('data.usuario.etiqueta', 'Automático')
            ->assertJsonPath('data.automatico', true)
            ->assertJsonPath('data.docente_examen.nombre_completo', 'Marcelo Quiroga')
            ->assertJsonPath('data.docente_examen.cod_sis', '10452');
    }

    public function test_el_cierre_automatico_se_muestra_como_automatico(): void
    {
        $this->logRow(
            $this->docente,
            'FINALIZAR',
            'examen',
            ['id_examen' => 5, 'estado' => 'EN_CURSO'],
            ['id_examen' => 5, 'estado' => 'FINALIZADO', 'tipo_finalizacion' => 'AUTOMATICA']
        );

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.usuario.etiqueta', 'Automático')
            ->assertJsonPath('data.0.automatico', true);
    }

    public function test_el_inicio_y_el_cierre_manuales_siguen_firmados_por_el_docente(): void
    {
        $this->logRow(
            $this->docente,
            'INICIAR',
            'examen',
            ['estado' => 'EN_INGRESO'],
            ['estado' => 'EN_CURSO', 'tipo_inicio' => 'MANUAL'],
        );
        $this->logRow($this->docente, 'FINALIZAR', 'examen', ['estado' => 'EN_CURSO'], ['estado' => 'FINALIZADO']);

        $response = $this->getJson('/api/bitacora')->assertOk();

        $this->assertSame(['Marcelo Quiroga'], array_values(array_unique($response->json('data.*.usuario.etiqueta'))));
        $this->assertSame([false], array_values(array_unique($response->json('data.*.automatico'))));
    }

    public function test_filtrar_por_el_docente_tambien_devuelve_sus_operaciones_automaticas(): void
    {
        $this->logRow(
            $this->docente,
            'INICIAR',
            'examen',
            ['estado' => 'EN_INGRESO'],
            ['estado' => 'EN_CURSO', 'tipo_inicio' => 'AUTOMATICO'],
        );
        $this->logRow($this->docente, 'CREAR', 'examen', null, ['id_examen' => 1]);

        $response = $this->getJson('/api/bitacora?q=10452')->assertOk()->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing([true, false], $response->json('data.*.automatico'));
    }

    public function test_una_cuenta_de_sistema_figura_como_sistema(): void
    {
        $system = User::findOrFail((string) app(SystemActor::class)->id());
        $this->assertContains($system->cod_sis, config('sciem.cuentas_sistema'));

        $id = $this->logRow($system, 'CREAR', 'ambiente', null, ['id_ambiente' => 1]);

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.usuario.tipo', 'sistema')
            ->assertJsonPath('data.0.usuario.etiqueta', 'Sistema')
            ->assertJsonPath('data.0.automatico', false);

        $this->getJson("/api/bitacora/{$id}")->assertOk()->assertJsonPath('data.usuario.etiqueta', 'Sistema');
    }

    public function test_las_cuentas_de_sistema_se_reconocen_por_la_configuracion(): void
    {
        config(['sciem.cuentas_sistema' => ['ADM0000X']]);
        $system = $this->namedAccount('Cuenta', 'Interna', 'ADM0000X');

        $this->logRow($system, 'CREAR', 'ambiente', null, ['id_ambiente' => 1]);

        $this->getJson('/api/bitacora')->assertOk()->assertJsonPath('data.0.usuario.etiqueta', 'Sistema');
    }

    public function test_una_cuenta_que_no_es_de_sistema_con_nombre_parecido_sigue_siendo_persona(): void
    {
        $other = $this->namedAccount('Sistema', 'Operativo', '30001', Role::DOCENTE);
        $this->logRow($other, 'CREAR', 'ambiente', null, ['id_ambiente' => 1]);

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.usuario.tipo', 'persona')
            ->assertJsonPath('data.0.usuario.etiqueta', 'Sistema Operativo');
    }
}
