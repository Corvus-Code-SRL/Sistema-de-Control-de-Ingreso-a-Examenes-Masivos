<?php

use App\Http\Controllers\Security\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// El login es la única ruta pública de la API.
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('auth.login');

// Todo lo demás exige el token de Sanctum. Un módulo nuevo queda protegido sin tocar nada aquí;
// RoutesRequireAuthenticationTest falla si alguna ruta queda fuera de este grupo.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Cada módulo declara sus rutas en routes/api/<modulo>.php.
    foreach (glob(base_path('routes/api/*.php')) as $moduleRoutes) {
        require $moduleRoutes;
    }
});
