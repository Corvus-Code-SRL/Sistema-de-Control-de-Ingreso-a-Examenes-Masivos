<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * RNF-14: el daemon autovacuum pasa antes por las tablas que más escriben.
 *
 * Por defecto limpia una tabla cuando el 20 % de sus filas está muerto y actualiza estadísticas al
 * 10 %. En SCIEM eso llega tarde:
 * - examen_estudiante recibe un UPDATE por cada estudiante que ingresa (cada uno deja una fila muerta);
 * - intento_ingreso y log solo crecen con cada verificación y cada operación auditada.
 *
 * Con 5 % y 2 % la limpieza y las estadísticas van al ritmo de un día de examen. Son parámetros de
 * almacenamiento: no cambian columnas, datos ni consultas.
 */
return new class extends Migration {
    public const TABLES = ['examen_estudiante', 'intento_ingreso', 'log'];

    public const SETTINGS = [
        'autovacuum_vacuum_scale_factor' => '0.05',
        'autovacuum_analyze_scale_factor' => '0.02',
    ];

    public function up(): void
    {
        $settings = collect(self::SETTINGS)
            ->map(fn (string $value, string $name) => "{$name} = {$value}")
            ->implode(', ');

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} SET ({$settings})");
        }
    }

    public function down(): void
    {
        $settings = implode(', ', array_keys(self::SETTINGS));

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} RESET ({$settings})");
        }
    }
};
