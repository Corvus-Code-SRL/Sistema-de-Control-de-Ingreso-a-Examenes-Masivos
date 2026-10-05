<?php

namespace App\Providers;

use App\Models\AccessToken;
use App\Services\Security\Contracts\SisGateway;
use App\Services\Security\FakeSisGateway;
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
    }

    public function boot()
    {
        Sanctum::usePersonalAccessTokenModel(AccessToken::class);
    }
}