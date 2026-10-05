<?php

use App\Http\Controllers\Academic\AssistantController;
use App\Http\Controllers\Academic\MyGroupsController;
use App\Http\Controllers\Academic\GroupController;
use App\Http\Controllers\Academic\PeriodController;
use App\Http\Controllers\Academic\StudentRosterController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\SubjectGroupController;
use App\Http\Controllers\Academic\SubjectCareerAssignmentController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: academic

Route::get('materias', [SubjectController::class, 'index']);

Route::get('materias/administracion', [SubjectController::class, 'adminIndex'])
    ->middleware('auth:sanctum');

Route::get('periodos', [PeriodController::class, 'index']);

// Endpoint legado de registro de materias.
Route::post('materias', [SubjectController::class, 'store'])->middleware('auth:sanctum');

Route::put('materias/{subject}', [SubjectController::class, 'update'])
    ->middleware('auth:sanctum');

Route::get(
    'carreras/{id_carrera}/materias/{id_materia}/grupos',
    [SubjectGroupController::class, 'index']
);

// HU-06: asignar una materia existente a una carrera.
Route::middleware('auth:sanctum')
    ->prefix('administracion')
    ->group(function () {
        Route::get(
            'carreras',
            [SubjectCareerAssignmentController::class, 'careers']
        );

        // {career} admite hasta 9 dígitos para que nunca exceda el rango de int4 de la base.
        Route::get(
            'carreras/{career}/materias-asignables',
            [SubjectCareerAssignmentController::class, 'assignableSubjects']
        )
            ->where('career', '[0-9]{1,9}');

        Route::post(
            'carreras/{career}/materias',
            [SubjectCareerAssignmentController::class, 'store']
        )
            ->where('career', '[0-9]{1,9}');
    });

Route::get('grupos/{id_grupo}', [GroupController::class, 'show']);
Route::post('grupos', [GroupController::class, 'store']);

Route::put('grupos/{id_grupo}', [GroupController::class, 'update']);

Route::post(
    'grupos/{id_grupo}/nomina/preview',
    [StudentRosterController::class, 'preview']
);

Route::post(
    'grupos/{id_grupo}/nomina/confirm',
    [StudentRosterController::class, 'confirm']
);

// HU-08: auxiliares del docente. {id_grupo} y {id_examen} son enteros; {id_usuario} es un uuid.
Route::prefix('docente')->group(function () {
    Route::get('auxiliares', [AssistantController::class, 'index']);
    Route::get('auxiliares/buscar', [AssistantController::class, 'search']);

    Route::get('grupos', [MyGroupsController::class, 'index']);

    Route::post('auxiliares/{id_usuario}/grupos', [AssistantController::class, 'addToGroups'])
        ->whereUuid('id_usuario');

    Route::post('grupos/{id_grupo}/auxiliares', [AssistantController::class, 'addToGroup'])
        ->whereNumber('id_grupo');
    Route::post('examenes/{id_examen}/auxiliares', [AssistantController::class, 'enableForExam'])
        ->whereNumber('id_examen');

    Route::delete('grupos/{id_grupo}/auxiliares/{id_usuario}', [AssistantController::class, 'removeFromGroup'])
        ->whereNumber('id_grupo')
        ->whereUuid('id_usuario');
    Route::delete('examenes/{id_examen}/auxiliares/{id_usuario}', [AssistantController::class, 'removeFromExam'])
        ->whereNumber('id_examen')
        ->whereUuid('id_usuario');
});
