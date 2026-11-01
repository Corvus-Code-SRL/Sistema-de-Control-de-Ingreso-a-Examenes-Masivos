<?php

use App\Http\Controllers\Exams\ExamInProgressController;
use App\Http\Controllers\Exams\AssistantClassroomController;
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\ExamHistoryController;
use App\Http\Controllers\Exams\ExamGroupController;
use App\Http\Controllers\Exams\GroupExamController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Exams\ClassroomController;
use App\Http\Controllers\Exams\FinalReportController;

// ...

Route::prefix('ambientes')->name('ambientes.')->group(function () {
    Route::get('/', [ClassroomController::class, 'index'])
         ->name('index');

    Route::post('/', [ClassroomController::class, 'store'])
         ->name('store');
});
// Rutas del módulo: exams

Route::prefix('historial/examenes')->name('historial.examenes.')->group(function () {
    Route::get('/', [ExamHistoryController::class, 'index'])->name('index');
    Route::get('/{exam}', [ExamHistoryController::class, 'show'])
        ->where('exam', '[0-9]{1,9}')
        ->name('show');
});

// HU-024 - Restringir los identificadores de examen antes del model binding.
Route::prefix('examenes')->name('examenes.')->group(function () {
    Route::get('/formulario', [ExamController::class, 'formOptions'])
         ->name('formulario');

    Route::get('/', [ExamController::class, 'index'])
         ->name('index');

    Route::post('/', [ExamController::class, 'store'])
        ->name('store');

    Route::get('/{exam}', [ExamController::class, 'show'])
        ->where('exam', '[0-9]{1,9}')
        ->name('show');

    Route::get('/{exam}/en-curso', [ExamInProgressController::class, 'show'])
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:polling')
        ->where('exam', '[0-9]{1,9}')
        ->name('en-curso');

    Route::put('/{exam}', [ExamController::class, 'update'])
        ->where('exam', '[0-9]{1,9}')
        ->name('update');

    Route::post('/{exam}/cancelar', [ExamController::class, 'cancel'])
        ->where('exam', '[0-9]{1,9}')
        ->name('cancelar');

    Route::post('/{exam}/iniciar', [ExamController::class, 'start'])
        ->where('exam', '[0-9]{1,9}')
        ->name('iniciar');

    Route::post('/{exam}/finalizar', [ExamController::class, 'finish'])
        ->where('exam', '[0-9]{1,9}')
        ->name('finalizar');

    Route::get('/{exam}/reporte-final', [FinalReportController::class, 'show'])
        ->where('exam', '[0-9]{1,9}')
        ->name('reporte-final');

    Route::get('/{exam}/reporte-final/pdf', [FinalReportController::class, 'pdf'])
        ->where('exam', '[0-9]{1,9}')
        ->name('reporte-final.pdf');

    Route::post('/{exam}/grupos', [ExamGroupController::class, 'store'])
        ->where('exam', '[0-9]{1,9}')
        ->name('grupos.store');

     // HU-09 — {user} es el id_usuario (uuid) del auxiliar habilitado.
    Route::get('/{exam}/auxiliares', [AssistantClassroomController::class, 'index'])
        ->where('exam', '[0-9]{1,9}')
        ->name('auxiliares.index');

    Route::put('/{exam}/auxiliares/{user}/ambiente', [AssistantClassroomController::class, 'update'])
        ->where('exam', '[0-9]{1,9}')
        ->whereUuid('user')
        ->name('auxiliares.ambiente');
});

// HU-029 — exámenes que incluyen un grupo propio (pestaña Exámenes del curso). El id se valida
// en el Request (como GET /grupos/{id}): uno no numérico o fuera del rango de int4 responde 422.
Route::get('grupos/{id_grupo}/examenes', [GroupExamController::class, 'index'])
     ->name('grupos.examenes');

// HU-09 — vista de solo lectura del auxiliar.
Route::get('auxiliar/examenes', [AssistantClassroomController::class, 'myExams'])
     ->name('auxiliar.examenes');
