<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\ExamGroupController;
use App\Http\Controllers\Exams\ExamFormController;
use App\Http\Controllers\Exams\ExamSchedulerController;


// Crear examen
Route::post('/new', [ExamController::class, 'create']);

// Obtener datos para formulario de examen
Route::get('/form-data', [ExamFormController::class, 'getFormData']);

// Obtener grupos por materia (HU-025 Criterio I1)
Route::get('/materias/{subjectId}/grupos', [ExamGroupController::class, 'getGroupsBySubject']);

// Asignar grupos a un examen (HU-025 Criterio I5)
Route::post('/{id}/grupos', [ExamGroupController::class, 'assignGroups']);  

// Obtener todos los exámenes programados
Route::get('/', [ExamSchedulerController::class, 'getAll']);
