<?php

namespace Tests\Feature\Maintenance;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RNF-14: las tablas de alta escritura tienen un autovacuum más frecuente, en la base y en el script. */
class AutovacuumSettingsTest extends TestCase
{
    private const TABLES = ['examen_estudiante', 'intento_ingreso', 'log'];

    private const SETTINGS = [
        'autovacuum_vacuum_scale_factor=0.05',
        'autovacuum_analyze_scale_factor=0.02',
    ];

    public function test_las_tablas_de_alta_escritura_tienen_el_autovacuum_ajustado(): void
    {
        foreach (self::TABLES as $table) {
            $options = DB::selectOne(
                "select coalesce(array_to_string(reloptions, ','), '') as opciones
                   from pg_class where oid = ?::regclass",
                ["public.{$table}"]
            )->opciones;

            foreach (self::SETTINGS as $setting) {
                $this->assertStringContainsString($setting, $options, "{$table}: falta {$setting}");
            }
        }
    }

    public function test_el_script_de_creacion_declara_el_ajuste(): void
    {
        $script = file_get_contents(base_path('../docs/database/creation-script.sql'));

        foreach (self::TABLES as $table) {
            $this->assertMatchesRegularExpression(
                "/ALTER TABLE public\\.{$table}\\s+SET \\(autovacuum_vacuum_scale_factor = 0\\.05, "
                . 'autovacuum_analyze_scale_factor = 0\\.02\\);/',
                $script,
                "El script no ajusta el autovacuum de {$table}."
            );
        }
    }
}
