<?php

use App\Http\Controllers\Security\UserController;
use App\Http\Controllers\Security\UserRoleController;
use Illuminate\Support\Facades\Route;

/*
 * Módulo Security — cuentas, roles, permisos y bitácora.
 */

// HU-001
Route::post('/usuarios', [UserController::class, 'store'])
     ->name('usuarios.store');

Route::get('/sis/verificar/{cod_sis}', [UserController::class, 'verificarSis']);

// HU-004
Route::get('/usuarios/{user}/rol', [UserRoleController::class, 'show'])
     ->name('usuarios.rol.show');

Route::post('/usuarios/{user}/rol', [UserRoleController::class, 'store'])
     ->name('usuarios.rol.store');

Route::get('/usuarios/{user}/asignaciones', [UserRoleController::class, 'asignaciones'])
     ->name('usuarios.asignaciones');