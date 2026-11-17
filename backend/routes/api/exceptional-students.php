<?php

use App\Http\Controllers\Academic\ExceptionalStudentController;
use Illuminate\Support\Facades\Route;

// HU-023: estudiantes excepcionales de UN examen. Usa la capacidad de casos especiales (special_cases): el Docente.
// {id_examen} admite hasta 9 dígitos para que nunca exceda el rango de int4 de la base.
Route::prefix('docente')->group(function () {
    Route::get('estudiantes-excepcionales/examenes', [ExceptionalStudentController::class, 'exams']);

    Route::get('examenes/{id_examen}/estudiantes-excepcionales/buscar', [ExceptionalStudentController::class, 'search'])
        ->where('id_examen', '[0-9]{1,9}');

    Route::post('examenes/{id_examen}/estudiantes-excepcionales', [ExceptionalStudentController::class, 'store'])
        ->where('id_examen', '[0-9]{1,9}');
});
