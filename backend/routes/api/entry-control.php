<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EntryControl\ControlIngresoController;
use App\Http\Controllers\EntryControl\SincronizacionController;


// Rutas del entry-control
Route::middleware(['auth:sanctum'])->prefix('control-ingreso')->group(function () {
    
    // Get de HU 10 para el Polling
    Route::get('/examenes/{examen}/estado', [ControlIngresoController::class, 'currentStatus'])
         ->whereNumber('examen')
         ->name('control-ingreso.examenes.estado');

    // Ruta para el guardado en offline -> mas adelante
    Route::post('/sincronizar', [SincronizacionController::class, 'sincronizateOffline'])
         ->name('control-ingreso.sincronizar');

});