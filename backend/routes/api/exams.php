<?php

use App\Http\Controllers\Exams\ExamController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: exams

// HU-24 — {exam} es el id_examen: cualquier otro valor responde 404 sin llegar a la base.
Route::prefix('examenes')->name('examenes.')->group(function () {
    Route::get('/formulario', [ExamController::class, 'formOptions'])
         ->name('formulario');

    Route::post('/', [ExamController::class, 'store'])
         ->name('store');

    Route::put('/{exam}', [ExamController::class, 'update'])
         ->whereNumber('exam')
         ->name('update');

    Route::post('/{exam}/cancelar', [ExamController::class, 'cancel'])
         ->whereNumber('exam')
         ->name('cancelar');
});
