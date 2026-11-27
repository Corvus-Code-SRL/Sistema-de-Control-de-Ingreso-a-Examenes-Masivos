<?php

namespace App\Providers;

use App\Models\AccessToken;
use App\Services\Academic\Importers\CsvStudentRosterReader;
use App\Services\Academic\Importers\XlsxStudentRosterReader;
use App\Services\Security\Contracts\SisGateway;
use App\Services\Security\FakeSisGateway;
use App\Support\Audit\AuditContext;
use App\Support\Database\SciemPostgresConnector;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        /*
         * Cuando alguien pida SisGateway por inyección de dependencias,
         * Laravel entrega la implementación configurada.
         */
        $this->app->bind(SisGateway::class, function () {
            return new FakeSisGateway();
        });

        // Sesión de la petición para la bitácora: una sola instancia por petición (la llena TrackAuditSession).
        $this->app->singleton(AuditContext::class);

        // RNF-14: conector propio de PostgreSQL (nombre de aplicación y límites de sesión).
        $this->app->bind('db.connector.pgsql', function ($app) {
            return new SciemPostgresConnector($app->runningInConsole());
        });

        // Los lectores de nómina no leen la configuración: reciben el tope por constructor.
        foreach ([CsvStudentRosterReader::class, XlsxStudentRosterReader::class] as $reader) {
            $this->app->when($reader)
                ->needs('$maxRows')
                ->give(function () {
                    return (int) config('sciem.nomina_max_filas');
                });
        }
    }

    public function boot()
    {
        Sanctum::usePersonalAccessTokenModel(AccessToken::class);
    }
}
