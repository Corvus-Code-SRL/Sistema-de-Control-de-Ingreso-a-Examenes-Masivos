<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\StoreIncidentReportRequest;
use App\Http\Requests\Incidents\StoreLateEntryIncidentReportRequest;
use App\Http\Resources\Incidents\IncidentReportResource;
use App\Services\Incidents\IncidentReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** Reportes de intentos rechazados: del control de ingreso (HU-032) y del ingreso con retraso (HU-037). */
class IncidentReportController extends Controller
{
    public const REPORTED_MESSAGE = 'Enviado a revisión del docente';

    public function store(StoreIncidentReportRequest $request, IncidentReportService $reports): JsonResponse
    {
        $input = $request->validated();
        $report = $reports->reportAttempt(
            (int) $input['examen'],
            (int) $input['intento'],
            $input,
            $request->file('evidencia'),
            $request->user()
        );

        return ApiResponse::created(new IncidentReportResource($report), self::REPORTED_MESSAGE);
    }

    public function storeLateEntry(
        StoreLateEntryIncidentReportRequest $request,
        IncidentReportService $reports
    ): JsonResponse {
        $input = $request->validated();
        $report = $reports->reportLateEntryAttempt(
            (int) $input['examen'],
            (int) $input['intento'],
            $input,
            $request->file('evidencia'),
            $request->user()
        );

        return ApiResponse::created(new IncidentReportResource($report), self::REPORTED_MESSAGE);
    }
}
