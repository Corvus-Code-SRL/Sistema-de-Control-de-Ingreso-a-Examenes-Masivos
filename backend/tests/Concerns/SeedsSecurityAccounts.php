<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\AdministratorAccountSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de roles y cuenta administradora para las pruebas de Security.
 *
 * La cuenta de config('sciem.usuario_prueba') actúa como el Administrador que
 * ejecuta las operaciones, igual que en desarrollo mientras no haya sesión real.
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

        $this->administratorId = (string) config('sciem.usuario_prueba');
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

    /** Hace que la operación la ejecute otra cuenta distinta del Administrador. */
    protected function actAs(User $user): void
    {
        config()->set('sciem.usuario_prueba', $user->id_usuario);
    }
}
