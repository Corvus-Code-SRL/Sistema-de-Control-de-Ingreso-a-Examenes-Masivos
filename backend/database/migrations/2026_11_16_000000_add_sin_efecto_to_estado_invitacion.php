<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el estado SIN_EFECTO al enum de las invitaciones a examen (HU-026).
 *
 * Una invitación queda sin efecto cuando el examen sale de PROGRAMADO sin que se haya respondido.
 *
 * PostgreSQL no permite usar un valor de enum recién agregado dentro de la misma transacción, por eso
 * esta migración va sola y fuera de transacción: el CHECK que lo menciona se crea en la siguiente
 * (2026_11_16_000001_prepare_invitacion_examen_for_collaborators). Es idempotente: repetirla no hace nada.
 *
 * Un valor de enum no se puede quitar, así que down() no revierte nada.
 */
class AddSinEfectoToEstadoInvitacion extends Migration
{
    public $withinTransaction = false;

    public function up()
    {
        DB::unprepared("ALTER TYPE public.estado_invitacion ADD VALUE IF NOT EXISTS 'SIN_EFECTO'");
    }

    public function down()
    {
        // Intencionalmente vacío: PostgreSQL no admite eliminar un valor de un enum.
    }
}
