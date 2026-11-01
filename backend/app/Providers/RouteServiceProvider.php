<?php

namespace App\Providers;

use App\Support\SisCode;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $this->sanctumUser($request);

            return $user === null
                ? Limit::perMinute(config('sciem.rate_limits.guest'))->by('ip:' . $request->ip())
                : Limit::perMinute(config('sciem.rate_limits.api'))->by('user:' . $user->getKey());
        });

        RateLimiter::for('polling', function (Request $request) {
            $user = $this->sanctumUser($request);

            return Limit::perMinute(config('sciem.rate_limits.polling'))
                ->by($user === null ? 'ip:' . $request->ip() : 'user:' . $user->getKey());
        });

        // El login es público: sin usuario, la clave es el código SIS (ya normalizado) más la IP.
        RateLimiter::for('login', function (Request $request) {
            $codSis = $request->input('cod_sis');
            $account = is_string($codSis) ? SisCode::normalize($codSis) : '';

            return [
                Limit::perMinute(config('sciem.rate_limits.login_account'))
                    ->by('login:' . sha1($account . '|' . $request->ip())),
                Limit::perMinute(config('sciem.rate_limits.login_ip'))->by('login-ip:' . $request->ip()),
            ];
        });
    }

    /**
     * Se resuelve con el guard de Sanctum para no depender del orden de los middleware: el guard
     * guarda el usuario de la petición, así que si `auth:sanctum` ya corrió no repite la consulta.
     */
    private function sanctumUser(Request $request): ?Authenticatable
    {
        return $request->user('sanctum');
    }
}
