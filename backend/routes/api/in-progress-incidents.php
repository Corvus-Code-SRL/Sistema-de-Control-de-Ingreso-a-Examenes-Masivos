<?php

use App\Http\Controllers\Incidents\InProgressIncidentController;
use Illuminate\Support\Facades\Route;

Route::post('examenes/{examen}/estudiantes/{estudiante}/incidencias', [InProgressIncidentController::class, 'store'])
    ->where(['examen' => '[0-9]{1,9}', 'estudiante' => '[0-9]{1,9}'])
    ->name('incidents.in-progress.store');

// HU-042: acceso «Reportar incidencia» (solo exámenes En curso); no depende de las rutas de HU-057.
Route::get('incidencias/examenes', [InProgressIncidentController::class, 'exams'])
    ->name('incidents.in-progress.exams');

// La pantalla consulta este contexto cada 5 s: va bajo el limitador `polling`, no bajo `api`.
Route::get('incidencias/examenes/{examen}/contexto', [InProgressIncidentController::class, 'context'])
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:polling')
    ->where('examen', '[0-9]{1,9}')
    ->name('incidents.in-progress.context');
