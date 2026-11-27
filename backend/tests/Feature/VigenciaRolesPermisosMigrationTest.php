<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * Migración 2026_11_26_000001 (RNF-01) sobre la forma anterior: se revierte, se siembran permisos sin fechas
 * y se vuelve a aplicar. El DDL de PostgreSQL es transaccional: todo se descarta al terminar la prueba.
 */
class VigenciaRolesPermisosMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    private const MIGRATION = '2026_11_26_000001_add_vigencia_to_permiso_rol.php';

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/' . self::MIGRATION);
        $this->seedSecurityAccounts();
    }

    private function migration(): \AddVigenciaToPermisoRol
    {
        return new \AddVigenciaToPermisoRol();
    }

    private function newPermission(): int
    {
        return (int) DB::table('permiso')->insertGetId(['nombre_permiso' => 'prueba_rnf01'], 'id_permiso');
    }

    public function test_permiso_rol_gana_fechas_y_conserva_las_filas_existentes(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('permiso_rol', 'fecha_inicio'));

        $permission = $this->newPermission();
        DB::table('permiso_rol')->insert([
            'id_permiso' => $permission,
            'id_rol' => $this->roleId(Role::DOCENTE),
            'estado' => 'ACTIVO',
        ]);

        $this->migration()->up();

        $row = DB::table('permiso_rol')->where('id_permiso', $permission)->first();
        $this->assertNotNull($row->fecha_inicio);
        $this->assertNull($row->fecha_fin);
    }

    public function test_la_fecha_fin_no_puede_ser_anterior_a_la_de_inicio(): void
    {
        $this->expectException(QueryException::class);

        DB::table('permiso_rol')->insert([
            'id_permiso' => $this->newPermission(),
            'id_rol' => $this->roleId(Role::DOCENTE),
            'estado' => 'ACTIVO',
            'fecha_inicio' => '2026-11-10 00:00:00+00',
            'fecha_fin' => '2026-11-01 00:00:00+00',
        ]);
    }

    public function test_el_mismo_par_puede_tener_varios_tramos_cerrados_y_uno_abierto(): void
    {
        $permission = $this->newPermission();
        $role = $this->roleId(Role::DOCENTE);

        DB::table('permiso_rol')->insert([
            ['id_permiso' => $permission, 'id_rol' => $role, 'estado' => 'ACTIVO',
                'fecha_inicio' => '2026-06-01 00:00:00+00', 'fecha_fin' => '2026-08-01 00:00:00+00'],
            ['id_permiso' => $permission, 'id_rol' => $role, 'estado' => 'ACTIVO',
                'fecha_inicio' => '2026-09-01 00:00:00+00', 'fecha_fin' => null],
        ]);

        $this->assertSame(2, DB::table('permiso_rol')->where('id_permiso', $permission)->count());
    }
}
