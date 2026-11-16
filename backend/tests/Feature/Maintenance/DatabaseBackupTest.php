<?php

namespace Tests\Feature\Maintenance;

use App\Console\Commands\BackupDatabaseCommand;
use App\Exceptions\Maintenance\DatabaseBackupException;
use App\Services\Maintenance\DatabaseBackupService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * sciem:respaldar con pg_dump simulado: no se ejecuta ningún programa. El respaldo y la restauración reales
 * se prueban a mano (docs/database/respaldo-y-restauracion.md).
 */
class DatabaseBackupTest extends DatabaseBackupTestCase
{
    public function test_respaldar_guarda_un_dump_con_la_hora_de_la_paz(): void
    {
        Carbon::setTestNow('2026-11-11 07:15:30', 'UTC');

        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $this->assertSame(['sciem_20261111_031530.dump'], $this->files());
    }

    public function test_respaldar_usa_formato_custom_solo_del_esquema_public_y_la_clave_va_por_el_entorno(): void
    {
        $this->artisan('sciem:respaldar')->assertExitCode(0);

        [$call] = $this->process->callsTo('pg_dump');
        $config = DB::connection()->getConfig();

        $this->assertContains('--format=custom', $call['command']);
        $this->assertContains('--no-owner', $call['command']);
        $this->assertContains('--schema=public', $call['command']);
        $this->assertSame($config['database'], $call['env']['PGDATABASE']);
        $this->assertSame((string) $config['password'], $call['env']['PGPASSWORD'] ?? '');

        if ((string) $config['password'] !== '') {
            $this->assertStringNotContainsString((string) $config['password'], implode(' ', $call['command']));
        }
    }

    public function test_respaldar_avisa_que_el_dump_tiene_datos_personales_reales(): void
    {
        $this->artisan('sciem:respaldar')
            ->expectsOutput(BackupDatabaseCommand::PRIVACY_WARNING)
            ->assertExitCode(0);
    }

    public function test_una_ruta_de_windows_con_espacios_llega_tal_cual_al_programa(): void
    {
        $dump = 'C:\Program Files\PostgreSQL\15\bin\pg_dump.exe';
        config(['sciem.respaldo.pg_dump' => $dump]);

        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $calls = $this->process->callsTo('pg_dump');
        $this->assertCount(1, $calls);
        $this->assertSame($dump, $calls[0]['command'][0]);
    }

    public function test_el_directorio_puede_llevar_espacios_y_corchetes(): void
    {
        $this->directory = $this->temporaryDirectory('sciem [respaldos] con espacios ');
        config(['sciem.respaldo.directorio' => $this->directory]);
        Carbon::setTestNow('2026-11-11 07:15:30', 'UTC');

        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $this->assertSame(['sciem_20261111_031530.dump'], $this->files());
        $this->assertCount(1, $this->app->make(DatabaseBackupService::class)->backups());
    }

    public function test_si_pg_dump_falla_no_queda_ningun_archivo_y_el_comando_falla(): void
    {
        $this->process->dumpExitCode = 1;
        $this->process->dumpError = 'pg_dump: error: connection failed';

        $this->artisan('sciem:respaldar')
            ->expectsOutput(
                'No se pudo respaldar la base. pg_dump terminó con el código 1: pg_dump: error: connection failed'
            )
            ->assertExitCode(1);

        $this->assertSame([], $this->files());
    }

    public function test_si_pg_dump_no_existe_el_mensaje_dice_que_variable_revisar(): void
    {
        $this->process->dumpExitCode = 127;
        $this->process->dumpError = 'No se pudo ejecutar «pg_dump»: el programa no existe o no se puede ejecutar.';

        $this->artisan('sciem:respaldar')
            ->expectsOutput(
                'No se pudo respaldar la base. pg_dump terminó con el código 127: '
                . 'No se pudo ejecutar «pg_dump»: el programa no existe o no se puede ejecutar. '
                . 'Revise SCIEM_PG_DUMP y SCIEM_PG_RESTORE en el .env.'
            )
            ->assertExitCode(1);
    }

    public function test_el_mensaje_de_error_nunca_muestra_la_contrasena(): void
    {
        config(['database.connections.' . config('database.default') . '.password' => 'clave-secreta']);
        DB::purge();
        $this->process->dumpExitCode = 1;
        $this->process->dumpError = 'error con clave-secreta';

        $this->artisan('sciem:respaldar')
            ->expectsOutput('No se pudo respaldar la base. pg_dump terminó con el código 1: error con ********')
            ->assertExitCode(1);
    }

    public function test_un_respaldo_fallido_se_reporta_al_log(): void
    {
        $log = Log::spy();
        $this->process->dumpExitCode = 1;

        $this->artisan('sciem:respaldar')->assertExitCode(1);

        $log->shouldHaveReceived('error')->once();
    }

    public function test_databasebackupexception_se_reporta_y_no_esta_en_dont_report(): void
    {
        $log = Log::spy();

        $this->app->make(ExceptionHandler::class)->report(new DatabaseBackupException('respaldo fallido'));

        $log->shouldHaveReceived('error')->once();
    }

    public function test_deja_solo_los_respaldos_mas_recientes_segun_la_retencion(): void
    {
        foreach (range(1, 8) as $day) {
            $this->oldBackup(sprintf('202611%02d_031500', $day));
        }
        file_put_contents($this->directory . '/otro_archivo.txt', 'no se toca');
        Carbon::setTestNow('2026-11-11 07:15:00', 'UTC');

        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $this->assertSame([
            'otro_archivo.txt',
            'sciem_20261103_031500.dump',
            'sciem_20261104_031500.dump',
            'sciem_20261105_031500.dump',
            'sciem_20261106_031500.dump',
            'sciem_20261107_031500.dump',
            'sciem_20261108_031500.dump',
            'sciem_20261111_031500.dump',
        ], $this->files());
    }

    public function test_la_retencion_nunca_borra_el_respaldo_recien_creado(): void
    {
        config(['sciem.respaldo.retencion' => 3]);

        foreach (range(5, 12) as $day) {
            $this->oldBackup(sprintf('202611%02d_031500', $day));
        }

        // El reloj de la máquina quedó atrás: el nuevo respaldo es el de nombre más antiguo.
        Carbon::setTestNow('2026-11-01 07:15:00', 'UTC');

        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $this->assertSame([
            'sciem_20261101_031500.dump',
            'sciem_20261110_031500.dump',
            'sciem_20261111_031500.dump',
            'sciem_20261112_031500.dump',
        ], $this->files());
    }

    public function test_la_retencion_respeta_los_respaldos_protegidos(): void
    {
        config(['sciem.respaldo.retencion' => 1]);
        $this->oldBackup('20261101_031500');
        $this->oldBackup('20261102_031500');
        $this->oldBackup('20261103_031500');

        $removed = $this->app->make(DatabaseBackupService::class)
            ->prune([$this->directory . '/sciem_20261101_031500.dump']);

        $this->assertSame(['sciem_20261101_031500.dump', 'sciem_20261103_031500.dump'], $this->files());
        $this->assertSame([$this->directory . '/sciem_20261102_031500.dump'], $removed);
    }

    public function test_dos_respaldos_en_el_mismo_segundo_no_se_pisan(): void
    {
        Carbon::setTestNow('2026-11-11 07:15:00', 'UTC');

        $this->artisan('sciem:respaldar')->assertExitCode(0);
        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $this->assertSame(['sciem_20261111_031500.dump', 'sciem_20261111_031500_2.dump'], $this->files());
    }

    public function test_el_directorio_de_respaldos_vacio_usa_storage_app_respaldos(): void
    {
        config(['sciem.respaldo.directorio' => '']);

        $this->assertSame(
            rtrim(storage_path('app/respaldos'), '/\\'),
            $this->app->make(DatabaseBackupService::class)->directory()
        );
    }

    public function test_un_directorio_dentro_de_un_repositorio_de_git_se_rechaza_sin_crearlo(): void
    {
        $repository = $this->temporaryDirectory('sciem_repo_');
        mkdir($repository . '/.git');
        $directory = $repository . '/datos/respaldos';
        config(['sciem.respaldo.directorio' => $directory]);

        $this->expectException(DatabaseBackupException::class);
        $this->expectExceptionMessage('queda dentro de un repositorio de git');

        try {
            $this->app->make(DatabaseBackupService::class)->backup();
        } finally {
            $this->assertDirectoryDoesNotExist($directory);
            $this->assertSame([], $this->process->calls);
        }
    }

    public function test_el_comando_informa_cuando_el_directorio_esta_dentro_de_un_repositorio(): void
    {
        $repository = $this->temporaryDirectory('sciem_repo_');
        mkdir($repository . '/.git');
        config(['sciem.respaldo.directorio' => $repository . '/respaldos']);

        $this->artisan('sciem:respaldar')->assertExitCode(1);

        $this->assertSame([], $this->process->calls);
    }

    public function test_storage_app_dentro_de_un_repositorio_si_se_permite_por_estar_ignorado(): void
    {
        $repository = $this->temporaryDirectory('sciem_repo_');
        mkdir($repository . '/.git');
        mkdir($repository . '/storage/app', 0700, true);
        $this->app->useStoragePath($repository . '/storage');
        config(['sciem.respaldo.directorio' => $repository . '/storage/app/respaldos']);

        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $this->assertCount(1, glob($repository . '/storage/app/respaldos/sciem_*.dump'));
    }

    public function test_un_directorio_fuera_de_cualquier_repositorio_se_acepta_y_se_crea(): void
    {
        $directory = $this->temporaryDirectory('sciem_externo_') . '/unidad/respaldos';
        config(['sciem.respaldo.directorio' => $directory]);

        $this->artisan('sciem:respaldar')->assertExitCode(0);

        $this->assertDirectoryExists($directory);
        $this->assertCount(1, glob($directory . '/sciem_*.dump'));
    }
}
