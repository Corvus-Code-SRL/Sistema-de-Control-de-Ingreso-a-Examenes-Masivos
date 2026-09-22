<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['nombre_rol' => 'Administrador', 'descripcion' => 'Gestión global del sistema, seguridad y administración de usuarios.'],
            ['nombre_rol' => 'Docente',       'descripcion' => 'Gestión de exámenes, grupos y supervisión académica.'],
            ['nombre_rol' => 'Auxiliar',      'descripcion' => 'Apoyo en el control de ingreso y supervisión durante exámenes.'],
        ];

        foreach ($roles as $rol) {
            DB::table('rol')->updateOrInsert(['nombre_rol' => $rol['nombre_rol']], $rol);
        }
    }
}