<?php

namespace Database\Seeders;

use App\Support\SisCode;
use App\Support\SystemActor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Crea la cuenta de sistema (SystemActor), la que firma en la bitácora las escrituras sin
     * sesión (seeders y comandos). Sin esta fila, una escritura de la bitácora sin sesión viola
     * la FK fk_log_usuario, porque el id de SystemActor no correspondería a ningún usuario.
     *
     * Riesgo documentado (Sprint 3): la cuenta queda con SIS 000000000, contraseña `password` y rol
     * Administrador, así que en un entorno compartido es una puerta de entrada conocida.
     */
    public function run()
    {
        $id = app(SystemActor::class)->id();

        if (! $id) {
            return;   // sin UUID configurado, no hay nada que sembrar
        }

        DB::table('usuario')->updateOrInsert(
            ['id_usuario' => $id],
            [
                'nombre'           => 'Usuario',
                'apellido_paterno' => 'De Prueba',
                'apellido_materno' => 'Sprint 1',
                'correo'           => 'usuario.prueba@sciem.local',
                'contrasenia'      => Hash::make('password'),
                'cod_sis'          => SisCode::normalize('000000000'),
                'estado'           => 'ACTIVO',
            ]
        );
    }
}