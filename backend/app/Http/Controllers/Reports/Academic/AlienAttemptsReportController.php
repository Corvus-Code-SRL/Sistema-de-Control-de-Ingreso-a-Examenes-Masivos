<?php

namespace App\Http\Controllers\Reports\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\Academic\AlienAttemptsReportRequest;
use App\Http\Resources\Reports\Academic\AlienAttemptsReportResource;
use App\Services\Reports\Academic\AlienAttemptsReportPdfService;
use App\Services\Reports\Academic\AlienAttemptsReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AlienAttemptsReportController extends Controller
{
    private AlienAttemptsReportService $report;
    private AlienAttemptsReportPdfService $pdf;

    public function __construct(
        AlienAttemptsReportService $report,
        AlienAttemptsReportPdfService $pdf
    ) {
        $this->report = $report;
        $this->pdf = $pdf;
    }

    public function index(AlienAttemptsReportRequest $request): AnonymousResourceCollection
    {
        $result = $this->report->paginate($request->validated());

        return AlienAttemptsReportResource::collection($result['data'])
            ->additional(['meta' => $result['meta']]);
    }

    public function pdf(AlienAttemptsReportRequest $request): Response
    {
        $file = $this->pdf->render($this->report->pdf($request->validated()));

        return response($file['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $file['nombre_archivo'] . '"',
        ]);
    }
}
