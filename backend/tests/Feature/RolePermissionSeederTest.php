<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * RNF-01: permiso_rol guarda desde y hasta cuándo un rol tuvo cada permiso de config/sciem.php, y ui /
 * ui_permiso qué pantallas habilita cada permiso. El seeder corre en cada deploy: debe ser idempotente.
 */
class RolePermissionSeederTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
        DB::table('ui_permiso')->delete();
        DB::table('ui')->delete();
        DB::table('permiso_rol')->delete();
        DB::table('permiso')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<int, string> «permiso:rol» */
    private function pairs(bool $openOnly = true): array
    {
        return DB::table('permiso_rol')
            ->join('permiso', 'permiso.id_permiso', '=', 'permiso_rol.id_permiso')
            ->join('rol', 'rol.id_rol', '=', 'permiso_rol.id_rol')
            ->when($openOnly, fn ($query) => $query->whereNull('permiso_rol.fecha_fin'))
            ->orderBy('permiso.nombre_permiso')
            ->orderBy('rol.nombre_rol')
            ->get(['permiso.nombre_permiso', 'rol.nombre_rol'])
            ->map(fn ($row) => $row->nombre_permiso . ':' . $row->nombre_rol)
            ->all();
    }

    /** @return array<int, string> */
    private function expectedPairs(): array
    {
        $pairs = [];

        foreach (config('sciem.access') as $feature => $roles) {
            foreach ($roles as $role) {
                $pairs[] = $feature . ':' . $role;
            }
        }

        foreach (config('sciem.permisos_propios') as $name => $own) {
            $pairs[] = $name . ':' . $own['rol'];
        }

        sort($pairs);

        return $pairs;
    }

    /** @return array<int, string> «pantalla:permiso» activas */
    private function activeScreens(): array
    {
        return DB::table('ui_permiso')
            ->join('ui', 'ui.id_ui', '=', 'ui_permiso.id_ui')
            ->join('permiso', 'permiso.id_permiso', '=', 'ui_permiso.id_permiso')
            ->where('ui_permiso.estado', 'ACTIVO')
            ->orderBy('ui.nombre_ui')
            ->orderBy('permiso.nombre_permiso')
            ->get(['ui.nombre_ui', 'permiso.nombre_permiso'])
            ->map(fn ($row) => $row->nombre_ui . ':' . $row->nombre_permiso)
            ->all();
    }

    private function auditCount(string $table): int
    {
        return DB::table('log')->where('tabla_afectada', $table)->count();
    }

    public function test_abre_un_permiso_vigente_por_cada_par_de_config(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $permissions = count(config('sciem.access')) + count(config('sciem.permisos_propios'));

        $this->assertSame($this->expectedPairs(), $this->pairs());
        $this->assertSame($permissions, DB::table('permiso')->count());
        $this->assertSame(count($this->expectedPairs()), $this->auditCount('permiso_rol'));
        $this->assertSame($permissions, $this->auditCount('permiso'));
    }

    public function test_cada_permiso_habilita_las_pantallas_de_config(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $expected = [];
        foreach (config('sciem.capacidades') as $feature => $screens) {
            foreach ($screens as $screen) {
                $expected[] = $screen . ':' . $feature;
            }
        }
        foreach (config('sciem.permisos_propios') as $name => $own) {
            foreach ($own['capacidades'] as $screen) {
                $expected[] = $screen . ':' . $name;
            }
        }
        sort($expected);

        $this->assertSame($expected, $this->activeScreens());
        // Una pantalla que comparten varios permisos (ingreso.operar) es una sola fila de ui.
        $this->assertSame(1, DB::table('ui')->where('nombre_ui', 'ingreso.operar')->count());
    }

    public function test_es_idempotente(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $logs = DB::table('log')->count();
        $rows = DB::table('permiso_rol')->count() + DB::table('ui_permiso')->count() + DB::table('ui')->count();

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($logs, DB::table('log')->count());
        $this->assertSame(
            $rows,
            DB::table('permiso_rol')->count() + DB::table('ui_permiso')->count() + DB::table('ui')->count()
        );
    }

    public function test_quitar_un_permiso_cierra_su_fila_sin_borrarla(): void
    {
        Carbon::setTestNow('2026-11-01 12:00:00');
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-11-10 12:00:00');
        config(['sciem.access.risk_center' => [Role::DOCENTE]]);
        $this->seed(RolePermissionSeeder::class);

        $row = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id_permiso', '=', 'permiso_rol.id_permiso')
            ->where('permiso.nombre_permiso', 'risk_center')
            ->where('permiso_rol.id_rol', $this->roleId(Role::ADMINISTRADOR))
            ->first(['permiso_rol.fecha_inicio', 'permiso_rol.fecha_fin']);

        $this->assertNotNull($row, 'La fila del permiso quitado debe seguir existiendo.');
        $this->assertTrue(Carbon::parse($row->fecha_inicio)->equalTo('2026-11-01 12:00:00'));
        $this->assertTrue(Carbon::parse($row->fecha_fin)->equalTo('2026-11-10 12:00:00'));
        $this->assertNotContains('risk_center:' . Role::ADMINISTRADOR, $this->pairs());
        $this->assertContains('risk_center:' . Role::ADMINISTRADOR, $this->pairs(false));

        $closing = DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('log.tabla_afectada', 'permiso_rol')
            ->where('accion.operacion', 'MODIFICAR')
            ->get(['log.antiguo_valor', 'log.nuevo_valor']);

        $this->assertCount(1, $closing);
        $this->assertSame('risk_center', json_decode($closing[0]->nuevo_valor, true)['permiso']);
        $this->assertNull(json_decode($closing[0]->antiguo_valor, true)['fecha_fin']);
    }

    public function test_devolver_un_permiso_abre_un_tramo_nuevo_y_conserva_el_anterior(): void
    {
        Carbon::setTestNow('2026-11-01 12:00:00');
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-11-05 12:00:00');
        config(['sciem.access.audit_log' => []]);
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-11-09 12:00:00');
        config(['sciem.access.audit_log' => [Role::ADMINISTRADOR]]);
        $this->seed(RolePermissionSeeder::class);

        $spans = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id_permiso', '=', 'permiso_rol.id_permiso')
            ->where('permiso.nombre_permiso', 'audit_log')
            ->orderBy('permiso_rol.fecha_inicio')
            ->get(['permiso_rol.fecha_inicio', 'permiso_rol.fecha_fin']);

        $this->assertCount(2, $spans);
        $this->assertNotNull($spans[0]->fecha_fin);
        $this->assertNull($spans[1]->fecha_fin);
    }

    public function test_quitar_una_pantalla_la_desactiva_y_devolverla_la_reactiva(): void
    {
        $this->seed(RolePermissionSeeder::class);

        config(['sciem.capacidades.audit_log' => []]);
        $this->seed(RolePermissionSeeder::class);
        $this->assertNotContains('bitacora.consultar:audit_log', $this->activeScreens());
        $this->assertSame(1, DB::table('ui')->where('nombre_ui', 'bitacora.consultar')->count());

        config(['sciem.capacidades.audit_log' => ['bitacora.consultar']]);
        $this->seed(RolePermissionSeeder::class);
        $this->assertContains('bitacora.consultar:audit_log', $this->activeScreens());
        $this->assertSame(2, DB::table('log')->where('tabla_afectada', 'ui_permiso')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', 'MODIFICAR')->count());
    }

    public function test_una_capacidad_de_una_funcion_inexistente_se_rechaza(): void
    {
        config(['sciem.capacidades.funcion_que_no_existe' => ['algo.hacer']]);

        $this->expectException(\LogicException::class);

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_la_base_no_admite_dos_filas_vigentes_del_mismo_par(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $open = DB::table('permiso_rol')->whereNull('fecha_fin')->first();

        $this->expectException(QueryException::class);

        DB::table('permiso_rol')->insert([
            'id_permiso' => $open->id_permiso,
            'id_rol' => $open->id_rol,
            'estado' => 'ACTIVO',
            'fecha_inicio' => '2030-01-01 00:00:00+00',
        ]);
    }
}
