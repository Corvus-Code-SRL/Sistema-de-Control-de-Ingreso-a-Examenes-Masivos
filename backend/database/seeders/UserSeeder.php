<?php

namespace Database\Seeders;

use App\Support\CurrentUser;
use App\Support\SisCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Crea la cuenta de prueba usada como "actor" en la bitácora mientras
     * la autenticación real no exista (Sprint 1). Sin esta fila, cualquier
     * escritura en la bitácora sin sesión real viola la FK fk_log_usuario,
     * porque el usuario actual (CurrentUser) apunta a un UUID que antes
     * no correspondía a ningún usuario insertado.
     */
    public function run()
    {
        $id = app(CurrentUser::class)->id();

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