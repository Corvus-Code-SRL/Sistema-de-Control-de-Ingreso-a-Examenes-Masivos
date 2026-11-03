<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-015: listado de la bitácora y sus filtros (criterios 1 a 6 y 10).
 */
class AuditLogListTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    private User $marcelo;

    private User $docente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareAuditLog();

        $this->marcelo = $this->namedAccount('Marcelo', 'Quiroga', '10452', Role::DOCENTE);
        $this->docente = $this->namedAccount('José', 'Pérez', '20381', Role::DOCENTE);
    }

    /** Criterio 1 */
    public function test_cada_operacion_muestra_fecha_usuario_accion_modulo_y_registro_ordenados_por_fecha(): void
    {
        $first = $this->logRow(
            $this->docente,
            'CREAR',
            'examen',
            null,
            ['id_examen' => 12, 'estado' => 'PROGRAMADO'],
            '2026-10-02 15:00:00+00'
        );
        $second = $this->logRow(
            $this->marcelo,
            'MODIFICAR',
            'grupo',
            ['id_grupo' => 7, 'num_grupo' => '1'],
            ['id_grupo' => 7, 'num_grupo' => 'B'],
            '2026-10-03 15:00:00+00'
        );

        $response = $this->getJson('/api/bitacora')->assertOk();

        $response->assertJsonPath('data.0.id_log', $second)
            ->assertJsonPath('data.0.fecha_hora', '2026-10-03T15:00:00+00:00')
            ->assertJsonPath('data.0.usuario.nombre_completo', 'Marcelo Quiroga')
            ->assertJsonPath('data.0.usuario.cod_sis', '10452')
            ->assertJsonPath('data.0.usuario.etiqueta', 'Marcelo Quiroga')
            ->assertJsonPath('data.0.accion.codigo', 'MODIFICAR')
            ->assertJsonPath('data.0.accion.etiqueta', 'Modificar')
            ->assertJsonPath('data.0.modulo.clave', 'grupos')
            ->assertJsonPath('data.0.modulo.etiqueta', 'Grupos')
            ->assertJsonPath('data.0.registro.descripcion', 'Grupo #7')
            ->assertJsonPath('data.1.id_log', $first)
            ->assertJsonPath('data.1.usuario.cod_sis', '20381')
            ->assertJsonPath('data.1.modulo.clave', 'examenes')
            ->assertJsonPath('data.1.registro.descripcion', 'Examen #12');
    }

    public function test_a_igual_instante_manda_el_id_mas_alto_y_el_orden_es_estable(): void
    {
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $this->logRow(
                $this->marcelo,
                'CREAR',
                'ambiente',
                null,
                ['id_ambiente' => $i],
                '2026-10-02 15:00:00+00',
            );
        }

        $this->assertSame(
            array_reverse($ids),
            $this->getJson('/api/bitacora')->assertOk()->json('data.*.id_log')
        );
    }

    /** Criterio 2 */
    public function test_120_operaciones_se_reparten_de_a_50_por_pagina_y_se_puede_avanzar(): void
    {
        foreach (range(1, 120) as $i) {
            $this->logRow($this->marcelo, 'CREAR', 'ambiente', null, ['id_ambiente' => $i]);
        }

        $first = $this->getJson('/api/bitacora')->assertOk();
        $first->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.total', 120)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3);

        $second = $this->getJson('/api/bitacora?page=2')->assertOk();
        $second->assertJsonCount(50, 'data')->assertJsonPath('meta.current_page', 2);

        $this->getJson('/api/bitacora?page=3')->assertOk()->assertJsonCount(20, 'data');

        $this->assertSame(
            [],
            array_intersect($first->json('data.*.id_log'), $second->json('data.*.id_log'))
        );
    }

    public function test_el_cliente_no_puede_cambiar_el_tamano_de_pagina(): void
    {
        foreach (range(1, 60) as $i) {
            $this->logRow($this->marcelo, 'CREAR', 'ambiente', null, ['id_ambiente' => $i]);
        }

        $this->getJson('/api/bitacora?per_page=5')->assertOk()->assertJsonCount(50, 'data');
    }

    /** Criterio 3 */
    public function test_filtrar_por_nombre_o_codigo_sis_muestra_solo_las_operaciones_de_ese_usuario(): void
    {
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);
        $this->logRow($this->marcelo, 'MODIFICAR', 'grupo', ['id_grupo' => 1], ['id_grupo' => 2]);
        $this->logRow($this->docente, 'CREAR', 'examen', null, ['id_examen' => 2]);

        foreach (['marcelo', 'MARCELO', '10452', 'quiroga', 'marcelo quiroga'] as $term) {
            $response = $this->getJson('/api/bitacora?q=' . urlencode($term))->assertOk();

            $this->assertSame(
                ['10452'],
                array_values(array_unique($response->json('data.*.usuario.cod_sis'))),
                "q={$term}"
            );
            $response->assertJsonCount(2, 'data');
        }
    }

    public function test_la_busqueda_de_usuario_no_distingue_tildes_ni_mayusculas(): void
    {
        $this->logRow($this->docente, 'CREAR', 'examen', null, ['id_examen' => 2]);
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);

        foreach (['jose', 'JOSÉ', 'perez', 'pérez'] as $term) {
            $this->getJson('/api/bitacora?q=' . urlencode($term))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.usuario.cod_sis', '20381');
        }
    }

    public function test_un_comodin_escrito_en_la_busqueda_se_trata_como_texto(): void
    {
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);

        $this->getJson('/api/bitacora?q=' . urlencode('%'))->assertOk()->assertJsonCount(0, 'data');
    }

    /** Criterio 4 */
    public function test_elegir_modificar_muestra_solo_modificaciones(): void
    {
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);
        $this->logRow($this->marcelo, 'MODIFICAR', 'grupo', ['id_grupo' => 1], ['id_grupo' => 2]);
        $this->logRow($this->marcelo, 'INICIAR', 'examen', ['estado' => 'EN_INGRESO'], ['estado' => 'EN_CURSO']);
        $this->logRow($this->docente, 'MODIFICAR', 'ambiente', ['capacidad' => 10], ['capacidad' => 20]);

        $response = $this->getJson('/api/bitacora?accion=MODIFICAR')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(['MODIFICAR'], array_values(array_unique($response->json('data.*.accion.codigo'))));
    }

    /** Criterio 5 */
    public function test_elegir_examenes_muestra_solo_el_registro_el_inicio_y_el_fin_de_examenes(): void
    {
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);
        $this->logRow($this->marcelo, 'INICIAR', 'examen', ['estado' => 'EN_INGRESO'], ['estado' => 'EN_CURSO']);
        $this->logRow($this->marcelo, 'FINALIZAR', 'examen', ['estado' => 'EN_CURSO'], ['estado' => 'FINALIZADO']);
        $this->logRow($this->marcelo, 'MODIFICAR', 'grupo', ['id_grupo' => 1], ['id_grupo' => 2]);
        $this->logRow($this->marcelo, 'CREAR', 'ambiente', null, ['id_ambiente' => 3]);

        $response = $this->getJson('/api/bitacora?modulo=examenes')->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(['examenes'], array_values(array_unique($response->json('data.*.modulo.clave'))));
        $this->assertEqualsCanonicalizing(
            ['CREAR', 'INICIAR', 'FINALIZAR'],
            $response->json('data.*.accion.codigo')
        );
    }

    public function test_el_modulo_otros_agrupa_las_tablas_que_no_estan_mapeadas(): void
    {
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);
        $this->logRow($this->marcelo, 'CREAR', 'tabla_sin_modulo', null, ['id_tabla_sin_modulo' => 9]);

        $response = $this->getJson('/api/bitacora?modulo=otros')->assertOk()->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.modulo.clave', 'otros')
            ->assertJsonPath('data.0.modulo.etiqueta', 'Otros')
            ->assertJsonPath('data.0.registro.tabla', 'tabla_sin_modulo')
            ->assertJsonPath('data.0.registro.descripcion', 'tabla_sin_modulo #9');
    }

    public function test_cada_tabla_registrada_hoy_cae_en_un_modulo_del_filtro(): void
    {
        $modules = array_keys(config('sciem.bitacora.modulos'));

        foreach (config('sciem.bitacora.tablas') as $table => $meta) {
            $this->assertContains($meta['modulo'], $modules, $table);
        }
    }

    public function test_los_filtros_se_combinan(): void
    {
        $this->logRow(
            $this->marcelo,
            'MODIFICAR',
            'examen',
            ['id_examen' => 1, 'estado' => 'A'],
            ['id_examen' => 1, 'estado' => 'B'],
        );
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 2]);
        $this->logRow($this->marcelo, 'MODIFICAR', 'grupo', ['id_grupo' => 1], ['id_grupo' => 2]);
        $this->logRow(
            $this->docente,
            'MODIFICAR',
            'examen',
            ['id_examen' => 3, 'estado' => 'A'],
            ['id_examen' => 3, 'estado' => 'B'],
        );

        $this->getJson('/api/bitacora?q=marcelo&accion=MODIFICAR&modulo=examenes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registro.descripcion', 'Examen #1');
    }

    /** Criterio 6 */
    public function test_el_rango_es_de_fechas_de_la_paz_y_los_limites_son_semiabiertos(): void
    {
        // 00:00 de La Paz = 04:00 UTC.
        // 30/09 23:59:59
        $this->logRow(
            $this->marcelo,
            'CREAR',
            'ambiente',
            null,
            ['id_ambiente' => 1],
            '2026-10-01 03:59:59+00',
        );
        // 01/10 00:00:00
        $inStart = $this->logRow(
            $this->marcelo,
            'CREAR',
            'ambiente',
            null,
            ['id_ambiente' => 2],
            '2026-10-01 04:00:00+00',
        );
        $inMiddle = $this->logRow(
            $this->marcelo,
            'CREAR',
            'ambiente',
            null,
            ['id_ambiente' => 3],
            '2026-10-08 12:00:00+00',
        );
        // 15/10 23:59:59
        $inEnd = $this->logRow(
            $this->marcelo,
            'CREAR',
            'ambiente',
            null,
            ['id_ambiente' => 4],
            '2026-10-16 03:59:59+00',
        );
        // 16/10 00:00:00
        $this->logRow(
            $this->marcelo,
            'CREAR',
            'ambiente',
            null,
            ['id_ambiente' => 5],
            '2026-10-16 04:00:00+00',
        );

        $ids = $this->getJson('/api/bitacora?desde=2026-10-01&hasta=2026-10-15')->assertOk()->json('data.*.id_log');

        $this->assertSame([$inEnd, $inMiddle, $inStart], $ids);
    }

    /** @dataProvider sessionTimeZones */
    public function test_el_rango_da_lo_mismo_con_la_sesion_de_la_base_en_utc_o_en_la_paz(string $sessionZone): void
    {
        DB::statement("SET LOCAL TIME ZONE '{$sessionZone}'");

        $this->logRow($this->marcelo, 'CREAR', 'ambiente', null, ['id_ambiente' => 1], '2026-10-01 03:59:59+00');
        $inside = $this->logRow(
            $this->marcelo,
            'CREAR',
            'ambiente',
            null,
            ['id_ambiente' => 2],
            '2026-10-01 04:00:00+00',
        );
        $this->logRow($this->marcelo, 'CREAR', 'ambiente', null, ['id_ambiente' => 3], '2026-10-02 04:00:00+00');

        $response = $this->getJson('/api/bitacora?desde=2026-10-01&hasta=2026-10-01')->assertOk();

        $this->assertSame([$inside], $response->json('data.*.id_log'));
        $response->assertJsonPath('data.0.fecha_hora', '2026-10-01T04:00:00+00:00');
    }

    public function sessionTimeZones(): array
    {
        return ['UTC' => ['UTC'], 'La Paz' => ['America/La_Paz']];
    }

    public function test_solo_desde_o_solo_hasta_acotan_por_un_lado(): void
    {
        $old = $this->logRow($this->marcelo, 'CREAR', 'ambiente', null, ['id_ambiente' => 1], '2026-09-01 12:00:00+00');
        $new = $this->logRow($this->marcelo, 'CREAR', 'ambiente', null, ['id_ambiente' => 2], '2026-10-20 12:00:00+00');

        $this->assertSame([$new], $this->getJson('/api/bitacora?desde=2026-10-01')->json('data.*.id_log'));
        $this->assertSame([$old], $this->getJson('/api/bitacora?hasta=2026-09-30')->json('data.*.id_log'));
    }

    public function test_desde_posterior_a_hasta_se_rechaza_con_el_mensaje_del_equipo(): void
    {
        $this->getJson('/api/bitacora?desde=2026-10-15&hasta=2026-10-01')
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta.0', 'La fecha de inicio debe ser anterior a la fecha de fin.');
    }

    public function test_un_dia_suelto_es_un_rango_valido(): void
    {
        $this->getJson('/api/bitacora?desde=2026-10-15&hasta=2026-10-15')->assertOk();
    }

    /** @dataProvider invalidQueries */
    public function test_los_parametros_invalidos_se_rechazan(string $query, string $field): void
    {
        $this->getJson('/api/bitacora?' . $query)->assertStatus(422)->assertJsonValidationErrors($field);
    }

    public function invalidQueries(): array
    {
        return [
            'fecha inexistente' => ['desde=2026-02-31', 'desde'],
            'fecha con otro formato' => ['hasta=15/10/2026', 'hasta'],
            'modulo desconocido' => ['modulo=central-riesgo', 'modulo'],
            'accion en minusculas' => ['accion=modificar', 'accion'],
            'pagina cero' => ['page=0', 'page'],
            'pagina enorme' => ['page=99999999999', 'page'],
            'busqueda demasiado larga' => ['q=' . str_repeat('a', 101), 'q'],
        ];
    }

    /** Criterio 10 (la parte del servidor: sin coincidencias, lista vacía) */
    public function test_un_filtro_sin_coincidencias_devuelve_una_lista_vacia(): void
    {
        $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);

        $this->getJson('/api/bitacora?q=nadie-se-llama-asi')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_una_operacion_sin_accion_se_muestra_como_tal(): void
    {
        $id = $this->logRow($this->marcelo, 'CREAR', 'examen', null, ['id_examen' => 1]);
        DB::table('log')->where('id_log', $id)->update(['id_accion' => null]);

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.accion.codigo', null)
            ->assertJsonPath('data.0.accion.etiqueta', 'Sin acción');
    }

    public function test_un_codigo_de_accion_fuera_del_mapa_se_muestra_tal_cual(): void
    {
        DB::table('accion')->insert(
            ['operacion' => 'NUEVA_OPERACION', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'x'],
        );
        $this->logRow($this->marcelo, 'NUEVA_OPERACION', 'examen', null, ['id_examen' => 1]);

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.accion.codigo', 'NUEVA_OPERACION')
            ->assertJsonPath('data.0.accion.etiqueta', 'NUEVA_OPERACION');
    }

    public function test_el_registro_de_una_cuenta_se_nombra_con_su_nombre_y_codigo_sis(): void
    {
        $this->logRow(
            $this->admin,
            'ASIGNAR_ROL',
            'usuario_rol',
            null,
            ['id_usuario' => $this->marcelo->id_usuario, 'id_rol' => 2],
        );

        $this->getJson('/api/bitacora')
            ->assertOk()
            ->assertJsonPath('data.0.modulo.clave', 'roles')
            ->assertJsonPath('data.0.registro.descripcion', 'Rol de cuenta: Marcelo Quiroga (10452)');
    }

    public function test_la_lista_no_devuelve_los_valores_crudos_de_la_operacion(): void
    {
        $this->logRow(
            $this->marcelo,
            'MODIFICAR',
            'grupo',
            ['id_grupo' => 7, 'num_grupo' => 'ANTES-X'],
            ['id_grupo' => 7, 'num_grupo' => 'DESPUES-X'],
        );

        $body = $this->getJson('/api/bitacora')->assertOk()->getContent();

        $this->assertStringNotContainsString('antiguo_valor', $body);
        $this->assertStringNotContainsString('nuevo_valor', $body);
        $this->assertStringNotContainsString('ANTES-X', $body);
        $this->assertStringNotContainsString('DESPUES-X', $body);
    }
}
