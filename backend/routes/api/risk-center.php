<?php

use App\Http\Controllers\RiskCenter\Academic\RiskCenterEvidenceController;
use App\Http\Controllers\RiskCenter\Academic\RiskCenterRecordController;
use App\Http\Controllers\RiskCenter\Academic\RiskCenterRectificationController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: risk-center

// Central de Riesgo de estudiantes (HU-046): solo lectura, Docente y Administrador.
Route::prefix('central-riesgo')->group(function () {
    Route::get('/', [RiskCenterRecordController::class, 'index'])
        ->name('central-riesgo.index');

    Route::get('{id}', [RiskCenterRecordController::class, 'show'])
        ->where('id', '[0-9]{1,10}')
        ->name('central-riesgo.show');

    // La evidencia se lee del almacenamiento y se entrega autenticada; nunca se enlaza por URL pública.
    Route::get('{id}/evidencia', [RiskCenterEvidenceController::class, 'show'])
        ->where('id', '[0-9]{1,10}')
        ->name('central-riesgo.evidencia');
});

// Rectificación de registros confirmados (HU-062): solo el Administrador. Rectificar es retirar el registro de
// la Central. Es un prefijo aparte de central-riesgo para que esa consulta siga siendo de solo lectura. No hay
// rutas de edición, de borrado ni de reactivación: la única escritura es rectificar un registro activo.
Route::prefix('rectificacion-riesgo')->group(function () {
    Route::get('estudiantes', [RiskCenterRectificationController::class, 'students'])
        ->name('rectificacion-riesgo.estudiantes');

    Route::get('estudiantes/{id}/registros', [RiskCenterRectificationController::class, 'studentRecords'])
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.estudiantes.registros');

    Route::get('registros/{id}', [RiskCenterRectificationController::class, 'show'])
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.registros.show');

    Route::post('registros/{id}/rectificar', [RiskCenterRectificationController::class, 'rectify'])
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.registros.rectificar');
});
