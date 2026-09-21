<?php

use App\Http\Controllers\Security\RoleController;
use App\Http\Controllers\Security\UserController;
use App\Http\Controllers\Security\UserQueryController;
use App\Http\Controllers\Security\UserRoleController;
use Illuminate\Support\Facades\Route;

/*
 * Módulo Security — cuentas, roles, permisos y bitácora.
 */

// HU-001
Route::post('/usuarios', [UserController::class, 'store'])
     ->name('usuarios.store');

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
