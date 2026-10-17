<?php

use App\Http\Controllers\Incidents\FaultTypeController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: incidents

// Catálogo compartido por los reportes de estudiantes (HU-042) y de postulantes.
Route::get('tipos-falta', [FaultTypeController::class, 'index'])
     ->name('tipos-falta.index');
