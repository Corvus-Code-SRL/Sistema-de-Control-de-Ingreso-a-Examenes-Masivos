<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-015: detalle de una operación y diff campo por campo (criterio 7 y ajuste A).
 */
class AuditLogDetailTest extends TestCase
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

    /** Criterio 7 */
    public function test_la_modificacion_del_numero_de_un_grupo_muestra_el_campo_con_su_valor_anterior_y_nuevo(): void
    {
        $id = $this->logRow(
            $this->docente,
            'MODIFICAR',
            'grupo',
            ['id_grupo' => 7, 'num_grupo' => '1', 'id_gestion_periodo' => 3],
            ['id_grupo' => 7, 'num_grupo' => 'B', 'id_gestion_periodo' => 3],
            '2026-10-03 15:00:00+00'
        );

        $response = $this->getJson("/api/bitacora/{$id}")->assertOk();

        $response->assertJsonPath('data.id_log', $id)
            ->assertJsonPath('data.fecha_hora', '2026-10-03T15:00:00+00:00')
            ->assertJsonPath('data.usuario.cod_sis', '10452')
            ->assertJsonPath('data.accion.etiqueta', 'Modificar')
            ->assertJsonPath('data.modulo.etiqueta', 'Grupos')
            ->assertJsonPath('data.registro.descripcion', 'Grupo #7')
            ->assertJsonPath('data.registro.ids.id_grupo', 7)
            ->assertJsonCount(1, 'data.cambios')
            ->assertJsonPath('data.cambios.0.campo', 'num_grupo')
            ->assertJsonPath('data.cambios.0.etiqueta', 'Número de grupo')
            ->assertJsonPath('data.cambios.0.anterior', '1')
            ->assertJsonPath('data.cambios.0.nuevo', 'B')
            ->assertJsonPath('data.cambios.0.oculto', false);
    }

    public function test_una_modificacion_lista_solo_los_campos_que_cambiaron(): void
    {
        $id = $this->logRow(
            $this->docente,
            'MODIFICAR',
            'examen',
            ['id_examen' => 5, 'nombre_examen' => 'Parcial', 'duracion' => 90, 'aula' => 'A1'],
            ['id_examen' => 5, 'nombre_examen' => 'Parcial 1', 'duracion' => 90, 'aula' => 'A2']
        );

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        // jsonb no conserva el orden de las claves: se compara por campo, no por posición.
        $this->assertEqualsCanonicalizing(['nombre_examen', 'aula'], $changes->keys()->all());
        $this->assertSame('Parcial', $changes['nombre_examen']['anterior']);
        $this->assertSame('Parcial 1', $changes['nombre_examen']['nuevo']);
        $this->assertSame('A1', $changes['aula']['anterior']);
        $this->assertSame('A2', $changes['aula']['nuevo']);
    }

    public function test_un_cambio_de_estado_muestra_los_campos_nuevos_con_anterior_vacio(): void
    {
        $id = $this->logRow(
            $this->docente,
            'INICIAR',
            'examen',
            ['id_examen' => 5, 'estado' => 'EN_INGRESO'],
            ['id_examen' => 5, 'estado' => 'EN_CURSO', 'tipo_inicio' => 'MANUAL']
        );

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        $this->assertSame(['estado', 'tipo_inicio'], $changes->keys()->all());
        $this->assertSame('EN_INGRESO', $changes['estado']['anterior']);
        $this->assertSame('EN_CURSO', $changes['estado']['nuevo']);
        $this->assertNull($changes['tipo_inicio']['anterior']);
        $this->assertSame('MANUAL', $changes['tipo_inicio']['nuevo']);
    }

    public function test_un_alta_muestra_los_campos_creados_con_anterior_vacio(): void
    {
        $id = $this->logRow(
            $this->docente,
            'CREAR',
            'ambiente',
            null,
            ['id_ambiente' => 4, 'nro_aula' => '101', 'capacidad' => 40],
        );

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        $this->assertEqualsCanonicalizing(['id_ambiente', 'nro_aula', 'capacidad'], $changes->keys()->all());
        $this->assertSame([null], $changes->pluck('anterior')->unique()->values()->all());
        $this->assertSame(4, $changes['id_ambiente']['nuevo']);
        $this->assertSame('101', $changes['nro_aula']['nuevo']);
        $this->assertSame(40, $changes['capacidad']['nuevo']);
        $this->assertSame('Número de aula', $changes['nro_aula']['etiqueta']);
    }

    public function test_una_baja_muestra_los_campos_eliminados_con_nuevo_vacio(): void
    {
        $id = $this->logRow(
            $this->docente,
            'QUITAR_AUXILIAR_EXAMEN',
            'examen_auxiliar',
            ['id_examen' => 5, 'id_usuario' => $this->docente->id_usuario],
            null
        );

        $changes = $this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios');

        $this->assertSame(['id_examen', 'id_usuario'], array_column($changes, 'campo'));
        $this->assertSame([5, $this->docente->id_usuario], array_column($changes, 'anterior'));
        $this->assertSame([null, null], array_column($changes, 'nuevo'));
        $this->assertSame('Identificador de examen', $changes[0]['etiqueta']);
    }

    public function test_los_valores_con_listas_se_devuelven_como_listas(): void
    {
        $id = $this->logRow(
            $this->docente,
            'CREAR',
            'examen',
            null,
            ['id_examen' => 5, 'ambientes' => [1, 2], 'grupos' => [7, 8, 9]]
        );

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        $this->assertSame([1, 2], $changes['ambientes']['nuevo']);
        $this->assertSame([7, 8, 9], $changes['grupos']['nuevo']);
    }

    public function test_un_cambio_sin_diferencias_no_inventa_campos(): void
    {
        $id = $this->logRow($this->docente, 'MODIFICAR', 'grupo', ['id_grupo' => 7], ['id_grupo' => 7]);

        $this->getJson("/api/bitacora/{$id}")->assertOk()->assertJsonCount(0, 'data.cambios');
    }

    public function test_el_detalle_no_devuelve_los_valores_crudos_de_la_operacion(): void
    {
        $id = $this->logRow(
            $this->docente,
            'MODIFICAR',
            'grupo',
            ['id_grupo' => 7, 'num_grupo' => '1'],
            ['id_grupo' => 7, 'num_grupo' => 'B'],
        );

        $body = $this->getJson("/api/bitacora/{$id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('antiguo_valor', $body);
        $this->assertStringNotContainsString('nuevo_valor', $body);
    }

    public function test_el_inicio_de_sesion_fallido_muestra_ip_y_motivo_tal_como_se_guardaron(): void
    {
        $id = $this->logRow(
            $this->docente,
            'INICIO_SESION_FALLIDO',
            'usuario',
            null,
            ['cod_sis' => '10452', 'ip' => '203.0.113.9', 'motivo' => 'credenciales_invalidas']
        );

        $response = $this->getJson("/api/bitacora/{$id}")->assertOk();

        $response->assertJsonPath('data.registro.descripcion', 'Cuenta: Marcelo Quiroga (10452)');

        $changes = collect($response->json('data.cambios'))->keyBy('campo');
        $this->assertSame('203.0.113.9', $changes['ip']['nuevo']);
        $this->assertSame('credenciales_invalidas', $changes['motivo']['nuevo']);
    }

    public function test_una_operacion_inexistente_responde_404(): void
    {
        $this->getJson('/api/bitacora/999999')->assertNotFound();
    }

    /** @dataProvider malformedIds */
    public function test_un_id_mal_formado_no_llega_al_controlador(string $id): void
    {
        $this->getJson("/api/bitacora/{$id}")->assertNotFound();
    }

    public function malformedIds(): array
    {
        return [['abc'], ['12abc'], ['-1'], ['1.5'], ['1234567890']];
    }
}
