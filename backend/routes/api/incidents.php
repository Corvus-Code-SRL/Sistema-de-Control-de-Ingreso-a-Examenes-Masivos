<?php

use App\Http\Controllers\Incidents\FaultTypeController;
use App\Http\Controllers\Incidents\IncidentReportController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: incidents

// Catálogo compartido por los reportes de estudiantes (HU-042) y de postulantes.
Route::get('tipos-falta', [FaultTypeController::class, 'index'])
     ->name('tipos-falta.index');

Route::post(
     'control-ingreso/examenes/{examen}/intentos/{intento}/reporte',
     [IncidentReportController::class, 'store']
)->where(['examen' => '[0-9]{1,9}', 'intento' => '[0-9]{1,9}'])
     ->name('control-ingreso.intentos.reporte.store');
