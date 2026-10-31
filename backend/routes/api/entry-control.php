<?php

use App\Http\Controllers\EntryControl\EntryAttemptController;
use App\Http\Controllers\EntryControl\EntryControlStatusController;
use App\Http\Controllers\EntryControl\LateEntryController;
use App\Http\Controllers\EntryControl\StudentEntryController;
use App\Http\Controllers\EntryControl\StudentVerificationController;
use App\Http\Controllers\Incidents\IncidentReportController;
use Illuminate\Support\Facades\Route;

// El control de ingreso registra QUÉ cuenta verifica cada ingreso. Quién puede controlar cada
// examen (su docente o un auxiliar habilitado) lo decide EntryAccessService.
Route::prefix('control-ingreso')->group(function () {
    Route::get('/examenes', [EntryControlStatusController::class, 'openExams'])
        ->name('control-ingreso.examenes.index');
    Route::prefix('examenes/{examen}')->where(['examen' => '[0-9]{1,9}'])->group(function () {
        Route::get('/intentos', [EntryAttemptController::class, 'index'])
            ->name('control-ingreso.examenes.intentos');
        Route::post('/intentos/{intento}/reporte', [IncidentReportController::class, 'store'])
            ->where('intento', '[0-9]{1,9}')
            ->name('control-ingreso.intentos.reporte.store');
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

        // HU-037 — ingreso con retraso mientras el examen está EN_CURSO.
        Route::prefix('retrasos')->group(function () {
            Route::post('/verificar', [LateEntryController::class, 'verify'])
                ->name('control-ingreso.examenes.retrasos.verificar');
            Route::get('/solicitudes', [LateEntryController::class, 'index'])
                ->name('control-ingreso.examenes.retrasos.solicitudes.index');
            Route::post('/solicitudes', [LateEntryController::class, 'store'])
                ->name('control-ingreso.examenes.retrasos.solicitudes.store');
            Route::post('/solicitudes/{solicitud}/autorizar', [LateEntryController::class, 'approve'])
                ->where('solicitud', '[0-9]{1,9}')
                ->name('control-ingreso.examenes.retrasos.solicitudes.autorizar');
            Route::post('/solicitudes/{solicitud}/rechazar', [LateEntryController::class, 'reject'])
                ->where('solicitud', '[0-9]{1,9}')
                ->name('control-ingreso.examenes.retrasos.solicitudes.rechazar');
            Route::post('/registrar', [LateEntryController::class, 'register'])
                ->name('control-ingreso.examenes.retrasos.registrar');
        });
    });
});
