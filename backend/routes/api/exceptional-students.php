<?php

use App\Http\Controllers\Academic\ExceptionalStudentController;
use Illuminate\Support\Facades\Route;

// HU-023: estudiantes excepcionales. Usa la capacidad de casos especiales (special_cases): el Docente.
// {id_grupo} admite hasta 9 dígitos para que nunca exceda el rango de int4 de la base.
Route::prefix('docente')->group(function () {
    Route::get('estudiantes-excepcionales/grupos', [ExceptionalStudentController::class, 'groups']);

    Route::get('grupos/{id_grupo}/estudiantes-excepcionales/buscar', [ExceptionalStudentController::class, 'search'])
        ->where('id_grupo', '[0-9]{1,9}');

    Route::post('grupos/{id_grupo}/estudiantes-excepcionales', [ExceptionalStudentController::class, 'store'])
        ->where('id_grupo', '[0-9]{1,9}');
});
