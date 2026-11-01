<?php

use App\Http\Controllers\Security\AuthController;
use App\Http\Controllers\Security\RoleController;
use App\Http\Controllers\Security\UserController;
use App\Http\Controllers\Security\UserQueryController;
use App\Http\Controllers\Security\UserRoleController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;

/*
 * Módulo Security — cuentas, roles, permisos y bitácora.
 */

// HU-001
Route::post('/usuarios', [UserController::class, 'store'])
     ->name('usuarios.store');

Route::get('/usuarios/codigo-invitado', [UserController::class, 'guestCode'])
     ->name('usuarios.codigo-invitado');

Route::get('/sis/verificar/{cod_sis}', [UserController::class, 'verificarSis']);

// HU-004 — {user} es un uuid: cualquier otro valor responde 404 sin llegar a la base.
Route::get('/roles', [RoleController::class, 'index'])
     ->name('roles.index');

Route::get('/usuarios', [UserQueryController::class, 'index'])
     ->name('usuarios.index');

Route::get('/usuarios/{user}', [UserQueryController::class, 'show'])
     ->whereUuid('user')
     ->name('usuarios.show');

Route::get('/usuarios/{user}/rol', [UserRoleController::class, 'show'])
     ->whereUuid('user')
     ->name('usuarios.rol.show');

Route::post('/usuarios/{user}/rol', [UserRoleController::class, 'store'])
     ->whereUuid('user')
     ->name('usuarios.rol.store');

Route::get('/usuarios/{user}/asignaciones', [UserRoleController::class, 'assignments'])
     ->whereUuid('user')
     ->name('usuarios.asignaciones');

// RNF-02 — el login es la única ruta pública y se declara en routes/api.php.
Route::prefix('auth')->name('auth.')->group(function () {
    // Una cuenta inactiva también puede cerrar su sesión: no pasa por EnsureAccountIsActive.
    Route::post('/logout', [AuthController::class, 'logout'])
        ->withoutMiddleware([EnsureAccountIsActive::class, EnsurePasswordIsChanged::class])
        ->name('logout');
    // HU-001: con contraseña temporal pendiente, estas dos son las únicas rutas (con logout) que responden.
    Route::get('/yo', [AuthController::class, 'me'])
        ->withoutMiddleware(EnsurePasswordIsChanged::class)
        ->name('yo');
    Route::post('/cambiar-contrasenia', [AuthController::class, 'changePassword'])
        ->withoutMiddleware(EnsurePasswordIsChanged::class)
        ->middleware('throttle:5,1,cambiar-contrasenia')
        ->name('cambiar-contrasenia');
    Route::post('/confirmar-password', [AuthController::class, 'confirmPassword'])->name('confirmar-password');
});
