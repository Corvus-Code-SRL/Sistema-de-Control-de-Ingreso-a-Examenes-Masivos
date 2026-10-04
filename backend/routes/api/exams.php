<?php

use App\Http\Controllers\Exams\AssistantClassroomController;
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\ExamGroupController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Exams\ClassroomController;

// ...

Route::prefix('ambientes')->name('ambientes.')->group(function () {
    Route::get('/', [ClassroomController::class, 'index'])
         ->name('index');

    Route::post('/', [ClassroomController::class, 'store'])
         ->name('store');
});
// Rutas del módulo: exams

// HU-24 — {exam} es el id_examen: cualquier otro valor responde 404 sin llegar a la base.
Route::prefix('examenes')->name('examenes.')->group(function () {
    Route::get('/formulario', [ExamController::class, 'formOptions'])
         ->name('formulario');

    Route::get('/', [ExamController::class, 'index'])
         ->name('index');

    Route::post('/', [ExamController::class, 'store'])
         ->name('store');

    Route::get('/{exam}', [ExamController::class, 'show'])
         ->whereNumber('exam')
         ->name('show');

    Route::put('/{exam}', [ExamController::class, 'update'])
         ->whereNumber('exam')
         ->name('update');

    Route::post('/{exam}/cancelar', [ExamController::class, 'cancel'])
         ->whereNumber('exam')
         ->name('cancelar');

    Route::post('/{exam}/finalizar', [ExamController::class, 'finish'])
         ->whereNumber('exam')
         ->name('finalizar');

    Route::post('/{exam}/grupos', [ExamGroupController::class, 'store'])
         ->whereNumber('exam')
         ->name('grupos.store');

    // HU-09 — {user} es el id_usuario (uuid) del auxiliar habilitado.
    Route::get('/{exam}/auxiliares', [AssistantClassroomController::class, 'index'])
         ->whereNumber('exam')
         ->name('auxiliares.index');

    Route::put('/{exam}/auxiliares/{user}/ambiente', [AssistantClassroomController::class, 'update'])
         ->whereNumber('exam')
         ->whereUuid('user')
         ->name('auxiliares.ambiente');
});

// HU-09 — vista de solo lectura del auxiliar.
Route::get('auxiliar/examenes', [AssistantClassroomController::class, 'myExams'])
     ->middleware('auth:sanctum')
     ->name('auxiliar.examenes');
