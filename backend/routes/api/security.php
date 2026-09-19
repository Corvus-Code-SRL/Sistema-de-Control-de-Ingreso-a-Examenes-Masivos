<?php

use App\Http\Controllers\Security\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Módulo Security — cuentas, roles, permisos y bitácora.
 */

// HU-001
Route::post('/usuarios', [UserController::class, 'store'])
     ->name('usuarios.store');