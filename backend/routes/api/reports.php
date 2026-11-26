<?php

use App\Http\Controllers\Reports\Academic\AlienAttemptsReportController;
use App\Http\Controllers\Reports\Security\AuditLogReportController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: reports

Route::prefix('reportes')->group(function () {
    // HU-068
    Route::get('bitacora', [AuditLogReportController::class, 'index'])
        ->name('reportes.bitacora');

    Route::get('bitacora/pdf', [AuditLogReportController::class, 'pdf'])
        ->name('reportes.bitacora.pdf');

    Route::get('bitacora/excel', [AuditLogReportController::class, 'excel'])
        ->name('reportes.bitacora.excel');

    Route::get('intentos-ajenos', [AlienAttemptsReportController::class, 'index'])
        ->name('reportes.intentos-ajenos');

    Route::get('intentos-ajenos/pdf', [AlienAttemptsReportController::class, 'pdf'])
        ->name('reportes.intentos-ajenos.pdf');
});
