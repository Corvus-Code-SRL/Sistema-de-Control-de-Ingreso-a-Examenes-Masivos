<?php

use App\Http\Controllers\Incidents\FaultTypeController;
use App\Http\Controllers\Incidents\IncidentReviewController;
use App\Http\Controllers\Incidents\IncidentReviewEvidenceController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: incidents

// Catálogo compartido por los reportes de estudiantes (HU-042) y de postulantes.
Route::get('tipos-falta', [FaultTypeController::class, 'index'])
     ->name('tipos-falta.index');

// HU-045: revisión de incidencias de estudiante.
Route::get('docente/incidencias', [IncidentReviewController::class, 'index'])
    ->name('incidencias.index');

Route::get('docente/incidencias/{reporte}', [IncidentReviewController::class, 'show'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.show');

Route::post('docente/incidencias/{reporte}/aprobar', [IncidentReviewController::class, 'approve'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.aprobar');

Route::post('docente/incidencias/{reporte}/rechazar', [IncidentReviewController::class, 'reject'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.rechazar');

Route::get('docente/incidencias/{reporte}/evidencia', [IncidentReviewEvidenceController::class, 'show'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.evidencia');