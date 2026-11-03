<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/** HU-015: valores de los selectores de acción y módulo. */
class AuditLogOptionsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareAuditLog();
    }

    public function test_ofrece_las_22_acciones_del_catalogo_con_su_etiqueta(): void
    {
        $actions = collect($this->getJson('/api/bitacora/opciones')->assertOk()->json('data.acciones'));

        $this->assertCount(22, $actions);
        $this->assertSame('Modificar', $actions->firstWhere('codigo', 'MODIFICAR')['etiqueta']);
        $this->assertSame('Retirar estudiante', $actions->firstWhere('codigo', 'EXPULSAR_ESTUDIANTE')['etiqueta']);
    }

    public function test_deshabilitar_y_habilitar_siguen_en_el_selector_aunque_nadie_los_escriba_aun(): void
    {
        $codes = $this->getJson('/api/bitacora/opciones')->assertOk()->json('data.acciones.*.codigo');

        $this->assertContains('DESHABILITAR', $codes);
        $this->assertContains('HABILITAR', $codes);
    }

    public function test_toda_accion_del_catalogo_tiene_etiqueta_en_la_configuracion(): void
    {
        $catalog = DB::table('accion')->pluck('operacion')->all();

        foreach ($catalog as $code) {
            $this->assertArrayHasKey($code, config('sciem.bitacora.etiquetas_accion'), $code);
        }
    }

    public function test_las_acciones_salen_ordenadas_por_etiqueta(): void
    {
        $labels = $this->getJson('/api/bitacora/opciones')->assertOk()->json('data.acciones.*.etiqueta');
        $sorted = $labels;
        usort($sorted, fn ($a, $b) => strcmp(Str::lower(Str::ascii($a)), Str::lower(Str::ascii($b))));

        $this->assertSame($sorted, $labels);
    }

    public function test_los_modulos_salen_en_orden_y_otros_va_al_final_sin_central_de_riesgo(): void
    {
        $modules = $this->getJson('/api/bitacora/opciones')->assertOk()->json('data.modulos');
        $keys = array_column($modules, 'clave');

        $this->assertSame(
            ['cuentas', 'roles', 'grupos', 'catalogo', 'ambientes', 'examenes', 'auxiliares', 'ingreso',
                'incidencias', 'situaciones', 'otros'],
            $keys
        );
        $this->assertSame('Exámenes', $modules[5]['etiqueta']);
        $this->assertSame('Otros', end($modules)['etiqueta']);
        $this->assertStringNotContainsString('riesgo', strtolower(json_encode($modules)));
    }

    public function test_una_accion_desconocida_del_catalogo_se_ofrece_con_su_codigo(): void
    {
        DB::table('accion')->insert(
            ['operacion' => 'NUEVA_OPERACION', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'x'],
        );

        $actions = collect($this->getJson('/api/bitacora/opciones')->assertOk()->json('data.acciones'));

        $this->assertSame('NUEVA_OPERACION', $actions->firstWhere('codigo', 'NUEVA_OPERACION')['etiqueta']);
    }
}
