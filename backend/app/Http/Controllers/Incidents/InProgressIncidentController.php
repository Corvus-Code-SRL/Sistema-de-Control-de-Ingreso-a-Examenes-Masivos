<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\BrowseInProgressIncidentExamsRequest;
use App\Http\Requests\Incidents\InProgressIncidentContextRequest;
use App\Http\Requests\Incidents\StoreInProgressIncidentRequest;
use App\Http\Resources\Incidents\IncidentReportResource;
use App\Http\Resources\Incidents\InProgressIncidentContextResource;
use App\Http\Resources\Incidents\SituationExamResource;
use App\Services\Incidents\InProgressIncidentService;
use App\Services\Incidents\InProgressIncidentWorkspaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InProgressIncidentController extends Controller
{
    public function store(StoreInProgressIncidentRequest $request, InProgressIncidentService $service): JsonResponse
    {
        $input = $request->validated();
        $report = $service->report(
            (int) $input['examen'],
            (int) $input['estudiante'],
            $input,
            $request->file('evidencia'),
            $request->user()
        );

        return ApiResponse::created(new IncidentReportResource($report), 'Enviado a revisión del docente');
    }

    public function exams(
        BrowseInProgressIncidentExamsRequest $request,
        InProgressIncidentWorkspaceService $workspace
    ): JsonResponse {
        $page = $workspace->exams($request->validated(), $request->user());

        return response()->json([
            'data' => SituationExamResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function context(
        InProgressIncidentContextRequest $request,
        InProgressIncidentWorkspaceService $workspace
    ): JsonResponse {
        $filters = $request->validated();
        $context = $workspace->context((int) $filters['examen'], $filters, $request->user());

        return ApiResponse::success(new InProgressIncidentContextResource($context));
    }
}
