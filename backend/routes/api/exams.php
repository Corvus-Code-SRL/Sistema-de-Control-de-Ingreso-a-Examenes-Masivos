<?php

use App\Http\Controllers\Exams\ExamInProgressController;
use App\Http\Controllers\Exams\AssistantClassroomController;
use App\Http\Controllers\Exams\CollaboratorInvitationController;
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\ExamTimerController;
use App\Http\Controllers\Exams\ExamHistoryController;
use App\Http\Controllers\Exams\ExamGroupController;
use App\Http\Controllers\Exams\GroupExamController;
use App\Http\Controllers\Exams\ReceivedInvitationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Exams\ClassroomController;
use App\Http\Controllers\Exams\FinalReportController;
use App\Http\Controllers\Exams\ExamEligibleListController;
use App\Http\Controllers\Exams\RoomDistributionController;

// ...

Route::prefix('ambientes')->name('ambientes.')->group(function () {
    Route::get('/', [ClassroomController::class, 'index'])
         ->name('index');

    Route::post('/', [ClassroomController::class, 'store'])
         ->name('store');
});
// Rutas del módulo: exams

Route::prefix('historial/examenes')->name('historial.examenes.')->group(function () {
    Route::get('/', [ExamHistoryController::class, 'index'])->name('index');
    Route::get('/{exam}', [ExamHistoryController::class, 'show'])
        ->where('exam', '[0-9]{1,9}')
        ->name('show');
});

// HU-024 - Restringir los identificadores de examen antes del model binding.
Route::prefix('examenes')->name('examenes.')->group(function () {
    Route::get('/formulario', [ExamController::class, 'formOptions'])
         ->name('formulario');

    // HU-028 — selector de exámenes para "Habilitar estudiantes".
    // Debe ir antes de GET /{exam} para no chocar con el binding.
    Route::get('/habilitados', [ExamEligibleListController::class, 'exams'])
        ->name('habilitados.selector');

    Route::get('/distribucion', [RoomDistributionController::class, 'exams'])
        ->name('distribucion.selector');

    Route::prefix('{exam}/distribucion')->where(['exam' => '[0-9]{1,9}'])->name('distribucion.')
        ->group(function (): void {
            Route::get('/', [RoomDistributionController::class, 'show'])->name('show');
            Route::post('/propuesta', [RoomDistributionController::class, 'proposal'])->name('propuesta');
            Route::put('/', [RoomDistributionController::class, 'save'])->name('save');
        });

    Route::get('/', [ExamController::class, 'index'])
         ->name('index');

    Route::post('/', [ExamController::class, 'store'])
        ->name('store');

    Route::get('/cronometros', [ExamTimerController::class, 'index'])
        ->withoutMiddleware('throttle:api')->middleware('throttle:polling')->name('cronometros');
    Route::get('/{exam}/cronometro', [ExamTimerController::class, 'show'])
        ->where('exam', '[0-9]{1,9}')->name('cronometro.show');
    Route::match(['post', 'put'], '/{exam}/cronometro', [ExamTimerController::class, 'save'])
        ->where('exam', '[0-9]{1,9}')->name('cronometro.save');

    Route::get('/{exam}', [ExamController::class, 'show'])
        ->where('exam', '[0-9]{1,9}')
        ->name('show');

    Route::get('/{exam}/en-curso', [ExamInProgressController::class, 'show'])
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:polling')
        ->where('exam', '[0-9]{1,9}')
        ->name('en-curso');

    Route::put('/{exam}', [ExamController::class, 'update'])
        ->where('exam', '[0-9]{1,9}')
        ->name('update');

    Route::post('/{exam}/cancelar', [ExamController::class, 'cancel'])
        ->where('exam', '[0-9]{1,9}')
        ->name('cancelar');

    Route::post('/{exam}/finalizar', [ExamController::class, 'finish'])
        ->where('exam', '[0-9]{1,9}')
        ->name('finalizar');

    Route::prefix('{exam}/habilitados')->name('habilitados.')->group(function () {
        Route::get('/', [ExamEligibleListController::class, 'current'])
            ->where('exam', '[0-9]{1,9}')
            ->name('current');

        Route::put('/', [ExamEligibleListController::class, 'save'])
            ->where('exam', '[0-9]{1,9}')
            ->name('save');

        Route::post('/preview', [ExamEligibleListController::class, 'preview'])
            ->where('exam', '[0-9]{1,9}')
            ->name('preview');

        Route::post('/confirm', [ExamEligibleListController::class, 'confirm'])
            ->where('exam', '[0-9]{1,9}')
            ->name('confirm');
    });

    Route::get('/{exam}/reporte-final', [FinalReportController::class, 'show'])
        ->where('exam', '[0-9]{1,9}')
        ->name('reporte-final');

    Route::get('/{exam}/reporte-final/pdf', [FinalReportController::class, 'pdf'])
        ->where('exam', '[0-9]{1,9}')
        ->name('reporte-final.pdf');

    Route::get('/{exam}/reporte-final/excel', [FinalReportController::class, 'excel'])
        ->where('exam', '[0-9]{1,9}')
        ->name('reporte-final.excel');

    Route::post('/{exam}/grupos', [ExamGroupController::class, 'store'])
        ->where('exam', '[0-9]{1,9}')
        ->name('grupos.store');

     // HU-09 — {user} es el id_usuario (uuid) del auxiliar habilitado.
    Route::get('/{exam}/auxiliares', [AssistantClassroomController::class, 'index'])
        ->where('exam', '[0-9]{1,9}')
        ->name('auxiliares.index');

    Route::put('/{exam}/auxiliares/{user}/ambiente', [AssistantClassroomController::class, 'update'])
        ->where('exam', '[0-9]{1,9}')
        ->whereUuid('user')
        ->name('auxiliares.ambiente');
});

// HU-029 — exámenes que incluyen un grupo propio (pestaña Exámenes del curso). El id se valida
// en el Request (como GET /grupos/{id}): uno no numérico o fuera del rango de int4 responde 422.
Route::get('grupos/{id_grupo}/examenes', [GroupExamController::class, 'index'])
     ->name('grupos.examenes');

// HU-09 — vista de solo lectura del auxiliar.
Route::get('auxiliar/examenes', [AssistantClassroomController::class, 'myExams'])
     ->name('auxiliar.examenes');

// HU-026 — invitar docentes colaboradores a un examen propio. La recepción y respuesta de las invitaciones
// (HU-027) vive en otras rutas.
Route::prefix('docente/invitaciones')->name('docente.invitaciones.')->group(function () {
    Route::get('/', [CollaboratorInvitationController::class, 'index'])
        ->name('index');

    Route::get('/examenes', [CollaboratorInvitationController::class, 'exams'])
        ->name('examenes');

    Route::get('/examenes/{exam}/docentes', [CollaboratorInvitationController::class, 'candidates'])
        ->where('exam', '[0-9]{1,9}')
        ->name('docentes');

    Route::post('/examenes/{exam}', [CollaboratorInvitationController::class, 'store'])
        ->where('exam', '[0-9]{1,9}')
        ->name('store');
});

// HU-027 — invitaciones recibidas: el docente invitado las consulta y las acepta (sumando sus grupos) o rechaza.
Route::prefix('invitaciones')->name('invitaciones.')->group(function () {
    Route::get('/', [ReceivedInvitationController::class, 'index'])
        ->name('index');

    Route::get('/{invitation}', [ReceivedInvitationController::class, 'show'])
        ->where('invitation', '[0-9]{1,9}')
        ->name('show');

    Route::post('/{invitation}/aceptar', [ReceivedInvitationController::class, 'accept'])
        ->where('invitation', '[0-9]{1,9}')
        ->name('aceptar');

    Route::post('/{invitation}/rechazar', [ReceivedInvitationController::class, 'reject'])
        ->where('invitation', '[0-9]{1,9}')
        ->name('rechazar');
});
