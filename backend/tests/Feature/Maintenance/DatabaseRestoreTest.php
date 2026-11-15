<?php

namespace Tests\Feature\Maintenance;

use App\Console\Commands\BackupDatabaseCommand;
use App\Exceptions\Maintenance\DatabaseBackupException;
use App\Services\Maintenance\DatabaseBackupService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\PendingCommand;

/**
 * sciem:restaurar con pg_restore simulado: la guarda SCIEM_RESTAURAR_PERMITIDO, la confirmación escrita
 * (host y base), --force solo en producción y --supabase sobre la base compartida de desarrollo.
 */
class DatabaseRestoreTest extends DatabaseBackupTestCase
{
    private const FILE = 'sciem_20261110_031500.dump';

    private const DISABLED = 'La restauración está deshabilitada en esta máquina. Ponga SCIEM_RESTAURAR_PERMITIDO=true '
        . 'en el .env solo mientras restaura y vuelva a ponerlo en false al terminar (con config:cache, ejecute '
        . 'php artisan config:cache de nuevo después de cada cambio).';

    private function service(): DatabaseBackupService
    {
        return $this->app->make(DatabaseBackupService::class);
    }

    /** @return array{host: string, database: string} */
    private function target(): array
    {
        return $this->service()->target();
    }

    private function question(): string
    {
        return 'Se reemplazarán los datos de la base «' . $this->target()['database'] . '» con «' . self::FILE
            . '». ¿Continuar?';
    }

    /** Responde las tres confirmaciones: host, nombre de la base y sí/no. */
    private function answer(PendingCommand $command, ?string $host = null, ?string $database = null): PendingCommand
    {
        return $command
            ->expectsQuestion('Escriba el host de la base para continuar', $host ?? $this->target()['host'])
            ->expectsQuestion(
                'Escriba el nombre de la base para continuar',
                $database ?? $this->target()['database']
            )
            ->expectsConfirmation($this->question(), 'yes');
    }

    /** @return array<int, array{command: array<int, string>, env: array<string, string>, list: string|null}> */
    private function restoreCalls(): array
    {
        return array_values(array_filter(
            $this->process->callsTo('pg_restore'),
            fn (array $call) => ! in_array('--list', $call['command'], true)
        ));
    }

    // ---------------------------------------------------------------- SCIEM_RESTAURAR_PERMITIDO

    public function test_la_restauracion_viene_apagada_por_defecto_en_la_configuracion_y_en_el_env_de_ejemplo(): void
    {
        $this->assertStringContainsString(
            "(bool) env('SCIEM_RESTAURAR_PERMITIDO', false)",
            (string) file_get_contents(config_path('sciem.php'))
        );
        $this->assertMatchesRegularExpression(
            '/^SCIEM_RESTAURAR_PERMITIDO=false\R/m',
            (string) file_get_contents(base_path('.env.example'))
        );
    }

    public function test_con_la_restauracion_deshabilitada_se_niega_antes_de_hacer_nada(): void
    {
        config(['sciem.respaldo.restaurar_permitido' => false]);
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsOutput(self::DISABLED)
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
        $this->assertSame([self::FILE], $this->files());
    }

    public function test_con_la_restauracion_deshabilitada_tampoco_ayuda_force_ni_estar_en_produccion(): void
    {
        config(['sciem.respaldo.restaurar_permitido' => false]);
        $this->app['env'] = 'production';
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', [
            'archivo' => self::FILE,
            '--force' => true,
            '--sin-respaldo-previo' => true,
        ])
            ->expectsOutput(self::DISABLED)
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_con_la_restauracion_deshabilitada_listar_los_respaldos_sigue_permitido(): void
    {
        config(['sciem.respaldo.restaurar_permitido' => false]);
        $this->oldBackup('20261109_031500');
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar')
            ->expectsOutput('  sciem_20261110_031500.dump')
            ->expectsOutput('  sciem_20261109_031500.dump')
            ->assertExitCode(0);
    }

    public function test_el_servicio_tambien_se_niega_a_restaurar_si_esta_deshabilitado(): void
    {
        config(['sciem.respaldo.restaurar_permitido' => false]);
        $this->oldBackup('20261110_031500');

        try {
            $this->service()->restore(self::FILE);
            $this->fail('Debió negarse a restaurar.');
        } catch (DatabaseBackupException $exception) {
            $this->assertSame(self::DISABLED, $exception->getMessage());
        }

        $this->assertSame([], $this->process->calls);
    }

    // ---------------------------------------------------------------- confirmación escrita

    public function test_restaurar_muestra_el_destino_pide_escribir_host_y_base_y_confirmar(): void
    {
        $this->oldBackup('20261110_031500');
        $target = $this->target();

        $command = $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsOutput('Se reemplazarán TODOS los datos de esta base:')
            ->expectsOutput('  Host: ' . $target['host'])
            ->expectsOutput('  Base: ' . $target['database']);

        $this->answer($command)
            ->expectsOutput('Base restaurada desde ' . self::FILE . '.')
            ->assertExitCode(0);
    }

    public function test_un_host_mal_escrito_cancela_sin_ejecutar_nada(): void
    {
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsQuestion('Escriba el host de la base para continuar', 'otro-host')
            ->expectsOutput('Restauración cancelada: el host no coincide. La base no cambió.')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_el_host_escrito_distingue_mayusculas_de_minusculas(): void
    {
        $this->useHost('Postgres-Prueba');
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsQuestion('Escriba el host de la base para continuar', 'postgres-prueba')
            ->expectsOutput('Restauración cancelada: el host no coincide. La base no cambió.')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_un_nombre_de_base_mal_escrito_cancela_sin_ejecutar_nada(): void
    {
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsQuestion('Escriba el host de la base para continuar', $this->target()['host'])
            ->expectsQuestion('Escriba el nombre de la base para continuar', 'otra_base')
            ->expectsOutput('Restauración cancelada: el nombre de la base no coincide. La base no cambió.')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_responder_no_a_la_confirmacion_final_cancela_sin_ejecutar_nada(): void
    {
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsQuestion('Escriba el host de la base para continuar', $this->target()['host'])
            ->expectsQuestion('Escriba el nombre de la base para continuar', $this->target()['database'])
            ->expectsConfirmation($this->question(), 'no')
            ->expectsOutput('Restauración cancelada. La base no cambió.')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_fuera_de_produccion_force_no_salta_la_confirmacion_escrita(): void
    {
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE, '--force' => true])
            ->expectsQuestion('Escriba el host de la base para continuar', 'otro-host')
            ->expectsOutput('Restauración cancelada: el host no coincide. La base no cambió.')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    // ---------------------------------------------------------------- producción

    public function test_en_produccion_exige_force(): void
    {
        $this->app['env'] = 'production';
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsOutput('En producción la restauración exige --force.')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_en_produccion_con_force_no_pide_confirmacion_pero_respalda_antes(): void
    {
        $this->app['env'] = 'production';
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE, '--force' => true])
            ->expectsOutput('Base restaurada desde ' . self::FILE . '.')
            ->assertExitCode(0);

        $this->assertCount(1, $this->process->callsTo('pg_dump'));
        $this->assertCount(1, $this->restoreCalls());
    }

    // ---------------------------------------------------------------- restauración

    public function test_restaurar_respalda_antes_y_no_borra_el_esquema_public(): void
    {
        $this->oldBackup('20261110_031500');

        $this->answer($this->artisan('sciem:restaurar', ['archivo' => self::FILE]))
            ->expectsOutput(BackupDatabaseCommand::PRIVACY_WARNING)
            ->expectsOutput('Base restaurada desde ' . self::FILE . '.')
            ->assertExitCode(0);

        $this->assertCount(1, $this->process->callsTo('pg_dump'));

        $restore = $this->restoreCalls();
        $this->assertCount(1, $restore);
        $this->assertContains('--clean', $restore[0]['command']);
        $this->assertContains('--single-transaction', $restore[0]['command']);
        $this->assertSame($this->directory . '/' . self::FILE, end($restore[0]['command']));
        $this->assertStringContainsString('TABLE public accion', (string) $restore[0]['list']);
        $this->assertStringNotContainsString('SCHEMA - public', (string) $restore[0]['list']);
        $this->assertStringNotContainsString('COMMENT - SCHEMA public', (string) $restore[0]['list']);
    }

    public function test_sin_respaldo_previo_no_ejecuta_pg_dump(): void
    {
        $this->oldBackup('20261110_031500');

        $this->answer($this->artisan('sciem:restaurar', [
            'archivo' => self::FILE,
            '--sin-respaldo-previo' => true,
        ]))->assertExitCode(0);

        $this->assertSame([], $this->process->callsTo('pg_dump'));
    }

    public function test_el_respaldo_previo_no_borra_el_que_se_esta_restaurando(): void
    {
        config(['sciem.respaldo.retencion' => 1]);
        $this->oldBackup('20261101_031500');
        $this->oldBackup('20261110_031500');
        Carbon::setTestNow('2026-11-11 07:15:00', 'UTC');

        $this->artisan('sciem:restaurar', ['archivo' => 'sciem_20261101_031500.dump'])
            ->expectsQuestion('Escriba el host de la base para continuar', $this->target()['host'])
            ->expectsQuestion('Escriba el nombre de la base para continuar', $this->target()['database'])
            ->expectsConfirmation(
                'Se reemplazarán los datos de la base «' . $this->target()['database']
                    . '» con «sciem_20261101_031500.dump». ¿Continuar?',
                'yes'
            )
            ->assertExitCode(0);

        // El más viejo (el que se restauró) y el respaldo previo siguen ahí; solo sobra el intermedio.
        $this->assertSame(['sciem_20261101_031500.dump', 'sciem_20261111_031500.dump'], $this->files());
        $command = $this->restoreCalls()[0]['command'];
        $this->assertSame($this->directory . '/sciem_20261101_031500.dump', end($command));
    }

    public function test_el_respaldo_previo_sobrevive_aunque_sea_el_de_nombre_mas_antiguo(): void
    {
        config(['sciem.respaldo.retencion' => 1]);
        $this->oldBackup('20261110_031500');
        Carbon::setTestNow('2026-11-01 07:15:00', 'UTC');

        $this->answer($this->artisan('sciem:restaurar', ['archivo' => self::FILE]))->assertExitCode(0);

        $this->assertSame(['sciem_20261101_031500.dump', self::FILE], $this->files());
    }

    public function test_un_respaldo_inexistente_se_rechaza_antes_de_preguntar(): void
    {
        $this->artisan('sciem:restaurar', ['archivo' => 'no_existe.dump'])
            ->expectsOutput('No se pudo restaurar. No existe el respaldo «no_existe.dump».')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_si_pg_restore_falla_el_comando_falla(): void
    {
        $this->oldBackup('20261110_031500');
        $this->process->restoreExitCode = 1;

        $this->answer($this->artisan('sciem:restaurar', [
            'archivo' => self::FILE,
            '--sin-respaldo-previo' => true,
        ]))
            ->expectsOutput('No se pudo restaurar. pg_restore terminó con el código 1: pg_restore: error')
            ->assertExitCode(1);
    }

    public function test_una_ruta_de_windows_con_espacios_llega_tal_cual_a_pg_restore(): void
    {
        $restore = 'C:\Program Files\PostgreSQL\15\bin\pg_restore.exe';
        config(['sciem.respaldo.pg_restore' => $restore]);
        $this->oldBackup('20261110_031500');

        $this->answer($this->artisan('sciem:restaurar', [
            'archivo' => self::FILE,
            '--sin-respaldo-previo' => true,
        ]))->assertExitCode(0);

        $calls = $this->process->callsTo('pg_restore');
        $this->assertCount(2, $calls);
        $this->assertSame($restore, $calls[0]['command'][0]);
        $this->assertSame($restore, $calls[1]['command'][0]);
    }

    public function test_sin_archivo_lista_los_respaldos_del_mas_reciente_al_mas_antiguo(): void
    {
        $this->oldBackup('20261109_031500');
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar')
            ->expectsOutput('  sciem_20261110_031500.dump')
            ->expectsOutput('  sciem_20261109_031500.dump')
            ->assertExitCode(0);
    }

    // ---------------------------------------------------------------- Supabase

    /** @return array<string, array{string}> */
    public function supabaseHosts(): array
    {
        return [
            'session pooler' => ['aws-0-sa-east-1.pooler.supabase.com'],
            'conexión directa' => ['db.proyectoprueba.supabase.co'],
            'mayúsculas' => ['DB.PROYECTOPRUEBA.SUPABASE.CO'],
        ];
    }

    /** @dataProvider supabaseHosts */
    public function test_sobre_supabase_se_exige_la_opcion_supabase(string $host): void
    {
        $this->useHost($host);
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE])
            ->expectsOutput(
                'La base está en Supabase («' . $host . '»), la base compartida de desarrollo. '
                . 'Restaurar allí reemplaza los datos de todo el equipo: agregue --supabase si es lo que quiere.'
            )
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_con_supabase_avisa_que_reemplaza_la_base_compartida_y_aun_pide_escribir_el_host(): void
    {
        $host = 'db.proyectoprueba.supabase.co';
        $this->useHost($host);
        $this->oldBackup('20261110_031500');

        $this->answer($this->artisan('sciem:restaurar', ['archivo' => self::FILE, '--supabase' => true]), $host)
            ->expectsOutput(
                'ATENCIÓN: esto reemplaza la base de desarrollo compartida en Supabase («' . $host . '»).'
            )
            ->expectsOutput('Base restaurada desde ' . self::FILE . '.')
            ->assertExitCode(0);
    }

    public function test_con_supabase_un_host_mal_escrito_sigue_cancelando(): void
    {
        $this->useHost('db.proyectoprueba.supabase.co');
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE, '--supabase' => true])
            ->expectsOutput(
                'ATENCIÓN: esto reemplaza la base de desarrollo compartida en Supabase '
                . '(«db.proyectoprueba.supabase.co»).'
            )
            ->expectsQuestion('Escriba el host de la base para continuar', 'supabase')
            ->expectsOutput('Restauración cancelada: el host no coincide. La base no cambió.')
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_en_produccion_force_no_reemplaza_la_opcion_supabase(): void
    {
        $this->app['env'] = 'production';
        $this->useHost('aws-0-sa-east-1.pooler.supabase.com');
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE, '--force' => true])
            ->expectsOutput(
                'La base está en Supabase («aws-0-sa-east-1.pooler.supabase.com»), la base compartida de '
                . 'desarrollo. Restaurar allí reemplaza los datos de todo el equipo: agregue --supabase si es lo '
                . 'que quiere.'
            )
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_la_opcion_supabase_no_salta_la_guarda_de_restauracion_deshabilitada(): void
    {
        config(['sciem.respaldo.restaurar_permitido' => false]);
        $this->useHost('db.proyectoprueba.supabase.co');
        $this->oldBackup('20261110_031500');

        $this->artisan('sciem:restaurar', ['archivo' => self::FILE, '--supabase' => true])
            ->expectsOutput(self::DISABLED)
            ->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    /** @return array<string, array{string, bool}> */
    public function hosts(): array
    {
        return [
            'dominio parecido (sufijo)' => ['notsupabase.co', false],
            'otro dominio con supabase dentro' => ['supabase.com.ejemplo.invalid', false],
            'servicio de docker' => ['postgres', false],
            'local' => ['127.0.0.1', false],
            'supabase.com a secas' => ['supabase.com', true],
            'con punto final' => ['db.x.supabase.co.', true],
        ];
    }

    /** @dataProvider hosts */
    public function test_solo_los_hosts_de_supabase_se_reconocen_como_tales(string $host, bool $expected): void
    {
        $this->assertSame($expected, $this->service()->isSupabaseHost($host));
    }

    public function test_un_host_parecido_a_supabase_no_exige_la_opcion(): void
    {
        $this->useHost('notsupabase.co');
        $this->oldBackup('20261110_031500');

        $this->answer($this->artisan('sciem:restaurar', ['archivo' => self::FILE]), 'notsupabase.co')
            ->assertExitCode(0);
    }
}
