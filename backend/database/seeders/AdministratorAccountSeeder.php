<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdministratorAccountSeeder extends Seeder
{
    /**
     * Da el rol Administrador a la cuenta de config('sciem.usuario_prueba').
     *
     * Sin autenticación real esa cuenta es quien ejecuta las operaciones, y la
     * gestión de roles exige que sea Administrador. Si ya tiene un rol vigente
     * no se toca. Requiere haber ejecutado antes UserSeeder y RoleSeeder.
     */
    public function run()
    {
        $userId = config('sciem.usuario_prueba');
        $roleId = DB::table('rol')->where('nombre_rol', Role::ADMINISTRADOR)->value('id_rol');

        if (! $userId || ! $roleId || ! DB::table('usuario')->where('id_usuario', $userId)->exists()) {
            return;
        }

        $hasActiveRole = DB::table('usuario_rol')
            ->where('id_usuario', $userId)
            ->whereNull('fecha_fin')
            ->exists();

        if (! $hasActiveRole) {
            DB::table('usuario_rol')->insert([
                'id_usuario'   => $userId,
                'id_rol'       => $roleId,
                'fecha_inicio' => now(),
            ]);
        }
    }
}
