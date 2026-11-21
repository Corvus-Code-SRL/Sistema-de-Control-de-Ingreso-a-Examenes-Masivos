<?php

use App\Http\Controllers\RiskCenter\Academic\RectificationRequestController;
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
// la Central: el Administrador envía una solicitud al docente que aprobó el reporte y solo si ese docente ya no
// está activo puede retirarlo sin su confirmación. Es un prefijo aparte de central-riesgo para que esa consulta
// siga siendo de solo lectura. No hay rutas de edición, de borrado ni de reactivación.
Route::prefix('rectificacion-riesgo')->group(function () {
    Route::get('estudiantes', [RiskCenterRectificationController::class, 'students'])
        ->name('rectificacion-riesgo.estudiantes');

    Route::get('estudiantes/{id}/registros', [RiskCenterRectificationController::class, 'studentRecords'])
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.estudiantes.registros');

    Route::get('registros/{id}', [RiskCenterRectificationController::class, 'show'])
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.registros.show');

    Route::post('registros/{id}/solicitudes', [RiskCenterRectificationController::class, 'requestRectification'])
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.registros.solicitudes');

    Route::post(
        'registros/{id}/rectificar-sin-confirmacion',
        [RiskCenterRectificationController::class, 'rectifyWithoutConfirmation']
    )
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.registros.rectificar-sin-confirmacion');

    Route::get('solicitudes/{id}/evidencia', [RiskCenterRectificationController::class, 'rejectionEvidence'])
        ->where('id', '[0-9]{1,9}')
        ->name('rectificacion-riesgo.solicitudes.evidencia');
});

// Solicitudes de rectificación (HU-062): el docente que aprobó el reporte las acepta o las rechaza. Las rutas fijas
// van antes que las de {id}.
Route::prefix('solicitudes-rectificacion')->group(function () {
    Route::get('/', [RectificationRequestController::class, 'index'])
        ->name('solicitudes-rectificacion.index');

    Route::get('conteo', [RectificationRequestController::class, 'count'])
        ->name('solicitudes-rectificacion.conteo');

    Route::post('{id}/aceptar', [RectificationRequestController::class, 'accept'])
        ->where('id', '[0-9]{1,9}')
        ->name('solicitudes-rectificacion.aceptar');

    Route::post('{id}/rechazar', [RectificationRequestController::class, 'reject'])
        ->where('id', '[0-9]{1,9}')
        ->name('solicitudes-rectificacion.rechazar');

    Route::get('{id}/evidencia-reporte', [RectificationRequestController::class, 'reportEvidence'])
        ->where('id', '[0-9]{1,9}')
        ->name('solicitudes-rectificacion.evidencia-reporte');

    Route::get('{id}/evidencia-rechazo', [RectificationRequestController::class, 'rejectionEvidence'])
        ->where('id', '[0-9]{1,9}')
        ->name('solicitudes-rectificacion.evidencia-rechazo');
});
