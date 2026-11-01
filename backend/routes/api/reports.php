<?php

use App\Http\Controllers\Reports\Academic\AlienAttemptsReportController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: reports

Route::prefix('reportes')->group(function () {
    Route::get('intentos-ajenos', [AlienAttemptsReportController::class, 'index'])
        ->name('reportes.intentos-ajenos');

    Route::get('intentos-ajenos/pdf', [AlienAttemptsReportController::class, 'pdf'])
        ->name('reportes.intentos-ajenos.pdf');
});
