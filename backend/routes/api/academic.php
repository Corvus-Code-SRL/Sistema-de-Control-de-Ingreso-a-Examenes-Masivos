<?php

use App\Http\Controllers\Academic\GroupController;
use App\Http\Controllers\Academic\StudentRosterController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\SubjectGroupController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: academic

Route::get('materias', [SubjectController::class, 'index']);

Route::get(
    'carreras/{id_carrera}/materias/{id_materia}/grupos',
    [SubjectGroupController::class, 'index']
);

Route::get('grupos/{id_grupo}', [GroupController::class, 'show']);

Route::post(
    'grupos/{id_grupo}/nomina/preview',
    [StudentRosterController::class, 'preview']
);

Route::post(
    'grupos/{id_grupo}/nomina/confirm',
    [StudentRosterController::class, 'confirm']
);