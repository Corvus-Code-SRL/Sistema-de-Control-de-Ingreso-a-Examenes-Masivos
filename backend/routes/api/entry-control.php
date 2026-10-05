<?php

use App\Http\Controllers\EntryControl\EntryControlStatusController;
use App\Http\Controllers\EntryControl\StudentEntryController;
use App\Http\Controllers\EntryControl\StudentVerificationController;
use Illuminate\Support\Facades\Route;

// El control de ingreso registra QUÉ cuenta verifica cada ingreso. Quién puede controlar cada
// examen (su docente o un auxiliar habilitado) lo decide EntryAccessService.
Route::prefix('control-ingreso')->group(function () {
    Route::get('/examenes', [EntryControlStatusController::class, 'openExams'])
        ->name('control-ingreso.examenes.index');
    Route::prefix('examenes/{examen}')->where(['examen' => '[0-9]+'])->group(function () {
        Route::get('/contexto', [EntryControlStatusController::class, 'context'])
            ->name('control-ingreso.examenes.contexto');
        Route::get('/buscar', [StudentVerificationController::class, 'search'])
            ->name('control-ingreso.examenes.buscar');
        Route::post('/verificar', [StudentVerificationController::class, 'verify'])
            ->name('control-ingreso.examenes.verificar');
        Route::post('/confirmar-ingreso', [StudentEntryController::class, 'confirm'])
            ->name('control-ingreso.examenes.confirmar');
        Route::post('/rechazar-ingreso', [StudentEntryController::class, 'reject'])
            ->name('control-ingreso.examenes.rechazar');
        Route::get('/estado', [EntryControlStatusController::class, 'currentStatus'])
            ->name('control-ingreso.examenes.estado');
    });
});
