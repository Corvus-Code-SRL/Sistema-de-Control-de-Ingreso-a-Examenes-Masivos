<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\FinalReportRequest;
use App\Http\Resources\Exams\FinalReportResource;
use App\Models\Exam;
use App\Services\Exams\FinalReportExcelService;
use App\Services\Exams\FinalReportPdfService;
use App\Services\Exams\FinalReportService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Consulta y descarga del reporte final de un examen finalizado (HU-051).
 */
class FinalReportController extends Controller
{
    private FinalReportService $reportService;

    private FinalReportPdfService $pdfService;

    private FinalReportExcelService $excelService;

    private CurrentUser $currentUser;

    public function __construct(
        FinalReportService $reportService,
        FinalReportPdfService $pdfService,
        FinalReportExcelService $excelService,
        CurrentUser $currentUser
    ) {
        $this->reportService = $reportService;
        $this->pdfService = $pdfService;
        $this->excelService = $excelService;
        $this->currentUser = $currentUser;
    }

    /** GET /api/examenes/{exam}/reporte-final */
    public function show(FinalReportRequest $request, Exam $exam): JsonResponse
    {
        $report = $this->reportService->build($exam, $this->currentUser->teacherId());

        return ApiResponse::success(new FinalReportResource($report));
    }

    /** GET /api/examenes/{exam}/reporte-final/pdf */
    public function pdf(FinalReportRequest $request, Exam $exam): Response
    {
        $report = $this->reportService->build($exam, $this->currentUser->teacherId());
        $pdf = $this->pdfService->render($report);

        return response($pdf['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $pdf['nombre_archivo'] . '"',
        ]);
    }

    /** GET /api/examenes/{exam}/reporte-final/excel */
    public function excel(FinalReportRequest $request, Exam $exam): Response
    {
        $report = $this->reportService->build($exam, $this->currentUser->teacherId());
        $excel = $this->excelService->render($report);

        return response($excel['contenido'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $excel['nombre_archivo'] . '"',
        ]);
    }
}