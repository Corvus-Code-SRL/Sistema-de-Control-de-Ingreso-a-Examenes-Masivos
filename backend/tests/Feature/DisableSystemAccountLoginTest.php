<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Migración de datos que cierra el acceso de la cuenta de sistema (SIS 000000000) en las bases que
 * la sembraron con la contraseña `password`.
 */
class DisableSystemAccountLoginTest extends TestCase
{
    use DatabaseTransactions;

    private const SYSTEM_ID = '00000000-0000-4000-8000-0000000000f1';
    private const ADMIN_ID = '00000000-0000-4000-8000-0000000000f2';
    private const TEACHER_ID = '00000000-0000-4000-8000-0000000000f3';

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_10_11_000000_disable_system_account_login.php');
    }

    private function migrate(): void
    {
        (new \DisableSystemAccountLogin())->up();
    }

    private function createAccount(string $id, string $codSis, string $password = 'password'): void
    {
        DB::table('usuario')->insert([
            'id_usuario' => $id,
            'nombre' => 'Cuenta',
            'apellido_paterno' => 'De Prueba',
            'correo' => "{$codSis}@sciem.test",
            'contrasenia' => Hash::make($password),
            'cod_sis' => $codSis,
            'estado' => 'ACTIVO',
        ]);
    }

    private function passwordHashOf(string $column, string $value): string
    {
        return (string) DB::table('usuario')->where($column, $value)->value('contrasenia');
    }

    /** @return array{usuarios: array, tokens: array} */
    private function snapshot(): array
    {
        return [
            'usuarios' => DB::table('usuario')->orderBy('id_usuario')->get()->map(fn ($row) => (array) $row)->all(),
            'tokens' => DB::table('personal_access_tokens')->orderBy('id')->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
        ];
    }

    public function test_cierra_el_login_de_la_cuenta_de_sistema_y_revoca_sus_tokens(): void
    {
        $this->seed(RoleSeeder::class);
        $this->createAccount(self::SYSTEM_ID, '000000000');
        DB::table('usuario_rol')->insert([
            'id_usuario' => self::SYSTEM_ID,
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
            'fecha_inicio' => now(),
        ]);
        // Estado de una base antigua: todavía no regía la regla que bloquea las cuentas de sistema.
        config(['sciem.cuentas_sistema' => []]);
        $this->postJson('/api/auth/login', ['cod_sis' => '000000000', 'password' => 'password'])->assertOk();
        User::findOrFail(self::SYSTEM_ID)->createToken('sesion');
        $this->assertSame(2, DB::table('personal_access_tokens')->count());

        config(['sciem.cuentas_sistema' => ['000000000']]);
        $this->migrate();

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->postJson('/api/auth/login', ['cod_sis' => '000000000', 'password' => 'password'])
            ->assertStatus(401)
            ->assertJsonPath('motivo', 'credenciales_invalidas');

        $row = DB::table('usuario')->where('id_usuario', self::SYSTEM_ID)->first();

        $this->assertNotNull($row, 'La fila no se borra: la bitácora la referencia.');
        $this->assertSame('ACTIVO', $row->estado);
        $this->assertSame(UserSeeder::UNUSABLE_PASSWORD_HASH, $row->contrasenia);
    }

    public function test_es_idempotente_la_segunda_ejecucion_no_cambia_nada(): void
    {
        $this->createAccount(self::SYSTEM_ID, '000000000');
        User::findOrFail(self::SYSTEM_ID)->createToken('sesion');

        $this->migrate();
        $afterFirst = $this->snapshot();

        $this->migrate();

        $this->assertSame($afterFirst, $this->snapshot());
    }

    public function test_no_toca_a_ninguna_otra_cuenta_ni_sus_tokens(): void
    {
        $this->createAccount(self::SYSTEM_ID, '000000000');
        $this->createAccount(self::ADMIN_ID, 'ADM0001');
        $this->createAccount(self::TEACHER_ID, '10452');
        User::findOrFail(self::TEACHER_ID)->createToken('sesion');

        $others = fn () => DB::table('usuario')
            ->whereIn('cod_sis', ['ADM0001', '10452'])
            ->orderBy('cod_sis')
            ->get()
            ->all();
        $before = $others();

        $this->migrate();

        $this->assertEquals($before, $others());
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->assertTrue(Hash::check('password', $this->passwordHashOf('cod_sis', 'ADM0001')));
    }

    public function test_filtra_por_cod_sis_y_nunca_por_id_usuario(): void
    {
        // En los datos de prueba el Administrador ADM0001 comparte id con la cuenta de sistema.
        $sharedId = (string) config('sciem.usuario_prueba');
        $this->createAccount($sharedId, 'ADM0001');
        User::findOrFail($sharedId)->createToken('sesion');
        $before = $this->snapshot();

        $this->migrate();

        $this->assertEquals($before, $this->snapshot());
        $this->assertTrue(Hash::check('password', $this->passwordHashOf('id_usuario', $sharedId)));
    }

    public function test_sin_cuenta_con_ese_cod_sis_no_hace_nada(): void
    {
        $this->createAccount(self::TEACHER_ID, '10452');
        $before = $this->snapshot();

        $this->migrate();

        $this->assertEquals($before, $this->snapshot());
    }

    public function test_usa_el_mismo_hash_que_el_seeder(): void
    {
        $constant = new ReflectionClassConstant(\DisableSystemAccountLogin::class, 'UNUSABLE_PASSWORD_HASH');

        $this->assertSame(UserSeeder::UNUSABLE_PASSWORD_HASH, $constant->getValue());
    }
}
