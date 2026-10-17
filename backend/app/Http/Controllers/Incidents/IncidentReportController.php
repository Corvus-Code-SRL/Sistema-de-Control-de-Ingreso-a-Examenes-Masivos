<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\StoreIncidentReportRequest;
use App\Http\Resources\Incidents\IncidentReportResource;
use App\Services\Incidents\IncidentReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class IncidentReportController extends Controller
{
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

        return ApiResponse::created(new IncidentReportResource($report), 'Enviado a revisión del docente');
    }
}