<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\FinalReportRequest;
use App\Http\Resources\Exams\FinalReportResource;
use App\Models\Exam;
use App\Services\Exams\FinalReportService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Consulta del reporte final de un examen finalizado (HU-051).
 */
class FinalReportController extends Controller
{
    private FinalReportService $reportService;

    private CurrentUser $currentUser;

    public function __construct(FinalReportService $reportService, CurrentUser $currentUser)
    {
        $this->reportService = $reportService;
        $this->currentUser = $currentUser;
    }

    /** GET /api/examenes/{exam}/reporte-final */
    public function show(FinalReportRequest $request, Exam $exam): JsonResponse
    {
        $report = $this->reportService->build($exam, $this->currentUser->teacherId());

        return ApiResponse::success(new FinalReportResource($report));
    }
}