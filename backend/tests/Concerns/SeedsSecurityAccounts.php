<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use App\Support\SystemActor;
use Database\Seeders\ActionSeeder;
use Database\Seeders\AdministratorAccountSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de roles y cuenta administradora para las pruebas de Security.
 *
 * Los seeders crean la cuenta de SystemActor con rol Administrador y la prueba actúa como ella
 * (Sanctum::actingAs). Para actuar como otra cuenta, actAs().
 */
trait SeedsSecurityAccounts
{
    protected string $administratorId;

    protected function seedSecurityAccounts(): void
    {
        $this->seed([
            UserSeeder::class,
            RoleSeeder::class,
            ActionSeeder::class,
            AdministratorAccountSeeder::class,
        ]);

        $this->administratorId = (string) app(SystemActor::class)->id();
        $this->actAsUserId($this->administratorId);
    }

    protected function roleId(string $roleName): int
    {
        return (int) Role::where('nombre_rol', $roleName)->value('id_rol');
    }

    /** Asigna un rol sin pasar por la API, para preparar el escenario. */
    protected function giveRole(User $user, string $roleName, string $since = '2026-01-10 08:00:00'): void
    {
        DB::table('usuario_rol')->insert([
            'id_usuario'   => $user->id_usuario,
            'id_rol'       => $this->roleId($roleName),
            'fecha_inicio' => $since,
        ]);
    }

    /** Cuenta nueva (contraseña `password`), con el rol indicado si se da; la cuenta es ACTIVA salvo que se pida otra. */
    protected function createAccount(?string $roleName = null, string $status = User::ESTADO_ACTIVO): User
    {
        $user = User::factory()->create(['estado' => $status]);

        if ($roleName !== null) {
            $this->giveRole($user, $roleName);
        }

        return $user;
    }

    /** Hace que la operación la ejecute otra cuenta distinta del Administrador. */
    protected function actAs(User $user): void
    {
        $this->actAsUserId($user->id_usuario);
    }
}
