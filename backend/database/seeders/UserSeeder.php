<?php

namespace Database\Seeders;

use App\Support\SisCode;
use App\Support\SystemActor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Hash bcrypt válido de un secreto aleatorio que nadie conoce: Hash::check() lo verifica sin
     * error y nunca coincide. No es un valor inválido a propósito (con hashing.verify, un hash no
     * bcrypt lanzaría una excepción y el login respondería 500). La migración
     * 2026_10_11_000000_disable_system_account_login lleva su propia copia.
     */
    public const UNUSABLE_PASSWORD_HASH = '$2y$10$gYra3xR5Z6gTh.3jZwPvB.XsimLMUuP92p4B5oiJ.Lqs5F59vDL3m';

    /**
     * Crea la cuenta de sistema (SystemActor), la que firma en la bitácora las escrituras sin
     * sesión (seeders y comandos). Sin esta fila, una escritura de la bitácora sin sesión viola
     * la FK fk_log_usuario, porque el id de SystemActor no correspondería a ningún usuario.
     *
     * Es una cuenta de firma, no de acceso: su contraseña es inutilizable, así que nadie puede
     * iniciar sesión con ella (RNF-02). Las pruebas actúan como ella con Sanctum::actingAs.
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
                'contrasenia'      => self::UNUSABLE_PASSWORD_HASH,
                'cod_sis'          => SisCode::normalize('000000000'),
                'estado'           => 'ACTIVO',
            ]
        );
    }
}
