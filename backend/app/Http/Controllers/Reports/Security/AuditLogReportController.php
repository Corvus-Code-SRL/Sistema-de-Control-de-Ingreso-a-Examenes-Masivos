<?php

namespace App\Http\Controllers\Reports\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\Security\AuditLogReportRequest;
use App\Http\Resources\Reports\Security\AuditLogReportResource;
use App\Services\Reports\Security\AuditLogReportService;
use App\Services\Reports\Security\AuditLogReportPdfService;
use App\Services\Reports\Security\AuditLogReportExcelService;
use App\Support\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * HU-068: audit log report endpoints.
 */
class AuditLogReportController extends Controller
{
    private AuditLogReportService $service;
    private AuditLogReportPdfService $pdf;
    private AuditLogReportExcelService $excel;

    public function __construct(
        AuditLogReportService $service,
        AuditLogReportPdfService $pdf,
        AuditLogReportExcelService $excel
    ) {
        $this->service = $service;
        $this->pdf = $pdf;
        $this->excel = $excel;
    }

    /**
     * Return the paginated audit log report.
     */
    public function index(
        AuditLogReportRequest $request
    ): AnonymousResourceCollection {
        $report = $this->service->generate(
            $request->validated()
        );

        return AuditLogReportResource::collection(
            $report['registros']
        )->additional([
            'totales' => $report['totales'],
            'filtros' => $report['filtros'],
            'puede_exportar' => $report['puede_exportar'],
        ]);
    }

    public function pdf(
        AuditLogReportRequest $request,
        CurrentUser $currentUser
    ): Response {
        $actorId = $currentUser->id();

        abort_if($actorId === null, 401);

        $report = $this->service->export(
            $request->validated(),
            $actorId
        );

        $file = $this->pdf->render($report);

        return response($file['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                . $file['nombre_archivo'] . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function excel(
        AuditLogReportRequest $request,
        CurrentUser $currentUser
    ): Response {
        $actorId = $currentUser->id();

        abort_if($actorId === null, 401);

        $report = $this->service->export(
            $request->validated(),
            $actorId
        );

        $file = $this->excel->render($report);

        return response($file['contenido'], 200, [
            'Content-Type' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'
                . $file['nombre_archivo'] . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}