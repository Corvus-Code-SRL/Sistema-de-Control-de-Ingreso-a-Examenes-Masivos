<?php

use App\Http\Controllers\Academic\GroupController;
use App\Http\Controllers\Academic\PeriodController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\SubjectGroupController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: academic

Route::get('materias', [SubjectController::class, 'index']);

Route::get('periodos', [PeriodController::class, 'index']);

Route::get(
    'carreras/{id_carrera}/materias/{id_materia}/grupos',
    [SubjectGroupController::class, 'index']
);

Route::get('grupos/{id_grupo}', [GroupController::class, 'show']);

Route::post('grupos', [GroupController::class, 'store']);