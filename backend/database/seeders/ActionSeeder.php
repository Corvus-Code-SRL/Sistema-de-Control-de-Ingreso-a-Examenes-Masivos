<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActionSeeder extends Seeder
{
    public function run()
    {
        $acciones = [
            ['operacion' => 'CREAR',        'tipo_operacion' => 'INSERT', 'descripcion' => 'Creación de un registro'],
            ['operacion' => 'MODIFICAR',    'tipo_operacion' => 'UPDATE', 'descripcion' => 'Modificación de un registro'],
            ['operacion' => 'DESHABILITAR', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'Cambio de estado a INACTIVO'],
            ['operacion' => 'HABILITAR',    'tipo_operacion' => 'UPDATE', 'descripcion' => 'Cambio de estado a ACTIVO'],
            ['operacion' => 'ASIGNAR_ROL',  'tipo_operacion' => 'INSERT', 'descripcion' => 'Asignación de rol a una cuenta'],
        ];

        foreach ($acciones as $accion) {
            DB::table('accion')->updateOrInsert(['operacion' => $accion['operacion']], $accion);
        }
    }
}