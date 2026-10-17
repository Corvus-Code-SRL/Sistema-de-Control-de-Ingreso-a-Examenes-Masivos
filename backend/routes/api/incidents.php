<?php

use App\Http\Controllers\Incidents\FaultTypeController;
use App\Http\Controllers\Incidents\IncidentReportController;
use App\Http\Controllers\Incidents\IncidentReportEvidenceController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo: incidents

// Catálogo compartido por los reportes de estudiantes (HU-042) y de postulantes.
Route::get('tipos-falta', [FaultTypeController::class, 'index'])
     ->name('tipos-falta.index');

// HU-045: revisión de incidencias de estudiante.
Route::get('docente/incidencias', [IncidentReportController::class, 'index'])
    ->name('incidencias.index');

Route::get('docente/incidencias/{reporte}', [IncidentReportController::class, 'show'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.show');

Route::post('docente/incidencias/{reporte}/aprobar', [IncidentReportController::class, 'approve'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.aprobar');

Route::post('docente/incidencias/{reporte}/rechazar', [IncidentReportController::class, 'reject'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.rechazar');

Route::get('docente/incidencias/{reporte}/evidencia', [IncidentReportEvidenceController::class, 'show'])
    ->where('reporte', '^estudiante-[0-9]{1,9}$')
    ->name('incidencias.evidencia');