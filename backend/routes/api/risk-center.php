<?php

use App\Http\Controllers\RiskCenter\Academic\RiskCenterEvidenceController;
use App\Http\Controllers\RiskCenter\Academic\RiskCenterRecordController;
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
