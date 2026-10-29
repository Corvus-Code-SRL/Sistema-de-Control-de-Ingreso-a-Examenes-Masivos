<?php

use App\Http\Controllers\Incidents\ExamSituationController;
use Illuminate\Support\Facades\Route;

Route::post('examenes/{examen}/estudiantes/{estudiante}/situaciones', [ExamSituationController::class, 'store'])
    ->where(['examen' => '[0-9]{1,9}', 'estudiante' => '[0-9]{1,9}'])
    ->name('situaciones.store');

Route::get('examenes/{examen}/situaciones', [ExamSituationController::class, 'index'])
    ->where('examen', '[0-9]{1,9}')
    ->name('situaciones.index');