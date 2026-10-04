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
            ['operacion' => 'CANCELAR',     'tipo_operacion' => 'UPDATE', 'descripcion' => 'Cancelación de un examen'],
            ['operacion' => 'INICIO_SESION_FALLIDO', 'tipo_operacion' => 'LOGIN', 'descripcion' => 'Intento fallido de inicio de sesión'],
            ['operacion' => 'ANADIR_AUXILIAR_GRUPO', 'tipo_operacion' => 'INSERT', 'descripcion' => 'Incorporación de un auxiliar a un grupo'],
            ['operacion' => 'HABILITAR_AUXILIAR_EXAMEN', 'tipo_operacion' => 'INSERT', 'descripcion' => 'Habilitación de un auxiliar para un examen'],
            ['operacion' => 'QUITAR_AUXILIAR_GRUPO', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'Baja de un auxiliar de un grupo'],
            ['operacion' => 'QUITAR_AUXILIAR_EXAMEN', 'tipo_operacion' => 'DELETE', 'descripcion' => 'Retiro de la habilitación de un auxiliar en un examen'],
        ];

        foreach ($acciones as $accion) {
            DB::table('accion')->updateOrInsert(['operacion' => $accion['operacion']], $accion);
        }
    }
}