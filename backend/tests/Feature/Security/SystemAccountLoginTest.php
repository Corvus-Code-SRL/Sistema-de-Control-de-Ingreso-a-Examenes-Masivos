<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\SystemActor;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RNF-02: la cuenta de sistema (SystemActor, SIS 000000000) firma la bitácora pero nadie puede
 * iniciar sesión con ella. La fila y su rol Administrador se conservan: la bitácora y las pruebas la usan.
 */
class SystemAccountLoginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tras_los_seeders_normales_000000000_con_password_recibe_el_rechazo_generico(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/auth/login', ['cod_sis' => '000000000', 'password' => 'password'])
            ->assertStatus(401)
            ->assertExactJson([
                'message' => 'Código SIS o contraseña incorrectos.',
                'motivo' => 'credenciales_invalidas',
            ]);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_la_fila_de_sistema_y_su_rol_se_conservan_con_un_hash_bcrypt_inutilizable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::findOrFail((string) app(SystemActor::class)->id());

        $this->assertSame('000000000', $user->cod_sis);
        $this->assertSame(User::ESTADO_ACTIVO, $user->estado);
        $this->assertSame('bcrypt', Hash::info($user->contrasenia)['algoName']);
        $this->assertSame(UserSeeder::UNUSABLE_PASSWORD_HASH, $user->contrasenia);
        $this->assertFalse(Hash::check('password', $user->contrasenia));
        $this->assertFalse(Hash::check('', $user->contrasenia));

        $this->assertTrue(
            DB::table('usuario_rol')
                ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
                ->where('usuario_rol.id_usuario', $user->id_usuario)
                ->whereNull('usuario_rol.fecha_fin')
                ->where('rol.nombre_rol', 'Administrador')
                ->exists(),
            'La cuenta de sistema debe conservar el rol Administrador.'
        );
    }

    public function test_el_seeder_reemplaza_una_contrasenia_conocida_dejada_por_una_version_anterior(): void
    {
        $this->seed(RoleSeeder::class);
        DB::table('usuario')->insert([
            'id_usuario' => (string) app(SystemActor::class)->id(),
            'nombre' => 'Usuario',
            'apellido_paterno' => 'De Prueba',
            'apellido_materno' => 'Sprint 1',
            'correo' => 'usuario.prueba@sciem.local',
            'contrasenia' => Hash::make('password'),
            'cod_sis' => '000000000',
            'estado' => 'ACTIVO',
        ]);

        $this->seed(UserSeeder::class);

        $this->postJson('/api/auth/login', ['cod_sis' => '000000000', 'password' => 'password'])
            ->assertStatus(401)
            ->assertJsonPath('motivo', 'credenciales_invalidas');
    }
}
