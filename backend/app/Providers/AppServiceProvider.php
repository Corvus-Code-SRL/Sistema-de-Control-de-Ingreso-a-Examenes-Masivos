<?php

namespace App\Providers;

use App\Services\Security\Contracts\SisGateway;
use App\Services\Security\FakeSisGateway;
use Illuminate\Support\ServiceProvider;

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
    }

    public function boot()
    {
        //
    }
}