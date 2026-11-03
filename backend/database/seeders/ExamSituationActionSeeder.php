<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Agrega las acciones de HU-057 sin reemplazar las del resto del equipo. */
class ExamSituationActionSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            [
                'operacion' => 'CAMBIAR_AMBIENTE',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Cambio de ambiente durante el examen',
            ],
            [
                'operacion' => 'OBSERVAR_IDENTIDAD',
                'tipo_operacion' => 'INSERT',
                'descripcion' => 'Observación de identidad durante el examen',
            ],
        ];

        foreach ($actions as $action) {
            DB::table('accion')->updateOrInsert(['operacion' => $action['operacion']], $action);
        }
    }
}