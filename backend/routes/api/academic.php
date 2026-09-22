<?php

use App\Http\Controllers\Academic\GroupController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\SubjectGroupController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: academic

Route::get('materias', [SubjectController::class, 'index']);

// Nuestra nueva ruta de la HU-006 protegida para que solo Administradores autenticados puedan crear
Route::post('materias', [SubjectController::class, 'store'])->middleware('auth:sanctum');

Route::put('materias/{subject}', [SubjectController::class, 'update'])
    ->middleware('auth:sanctum');

Route::get(
    'carreras/{id_carrera}/materias/{id_materia}/grupos',
    [SubjectGroupController::class, 'index']
);

Route::get('grupos/{id_grupo}', [GroupController::class, 'show']);