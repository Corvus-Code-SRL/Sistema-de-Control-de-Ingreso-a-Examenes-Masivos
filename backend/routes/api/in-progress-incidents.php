<?php

use App\Http\Controllers\Incidents\InProgressIncidentController;
use Illuminate\Support\Facades\Route;

Route::post('examenes/{examen}/estudiantes/{estudiante}/incidencias', [InProgressIncidentController::class, 'store'])
    ->where(['examen' => '[0-9]{1,9}', 'estudiante' => '[0-9]{1,9}'])
    ->name('incidents.in-progress.store');