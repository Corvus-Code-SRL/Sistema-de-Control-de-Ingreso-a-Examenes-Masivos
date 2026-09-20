<?php

use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\SubjectGroupController;
use App\Http\Controllers\Academic\StudentRosterController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: academic

Route::get('materias', [SubjectController::class, 'index']);

Route::get(
    'carreras/{id_carrera}/materias/{id_materia}/grupos',
    [SubjectGroupController::class, 'index']
);

Route::post(
    'grupos/{id_grupo}/nomina/preview',
    [StudentRosterController::class, 'preview']
);

Route::post(
    'grupos/{id_grupo}/nomina/confirm',
    [StudentRosterController::class, 'confirm']
);