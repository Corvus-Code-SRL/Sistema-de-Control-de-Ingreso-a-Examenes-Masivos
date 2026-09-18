<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Exams\ExamController;

// Rutas del módulo: exams

// ============================================================================================
// EXAMS - Exámenes
// ============================================================================================

// Crear examen
Route::post('/', [ExamController::class, 'crear']);

// Obtener datos para formulario de examen
Route::get('/form-data', [ExamController::class, 'obtenerDatosFormulario']);