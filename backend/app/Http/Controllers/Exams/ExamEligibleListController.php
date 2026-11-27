<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ConfirmEligibleListRequest;
use App\Http\Requests\Exams\PreviewEligibleListRequest;
use App\Http\Requests\Exams\ShowEligibleListRequest;
use App\Http\Requests\Exams\ListEligibleListExamsRequest;
use App\Http\Resources\Exams\EligibleListCurrentResource;
use App\Http\Resources\Exams\EligibleListImportResource;
use App\Http\Resources\Exams\EligibleListPreviewResource;
use App\Http\Resources\Exams\EligibleListExamOptionResource;
use App\Services\Exams\ExamEligibleListService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Lista de habilitados de un examen (HU-028).
 *
 * - GET /habilitados → EligibleListCurrentResource (nómina con su estado)
 * - POST /habilitados/preview → preview del archivo
 * - POST /habilitados/confirm → reemplaza la lista en una sola transacción
 */
class ExamEligibleListController extends Controller
{
    private ExamEligibleListService $service;

    private CurrentUser $currentUser;

    public function __construct(
        ExamEligibleListService $service,
        CurrentUser $currentUser
    ) {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    /**
     * GET /api/examenes/habilitados — selector de exámenes de HU-028.
     *
     * Solo exámenes del docente PROGRAMADOS con el control de ingreso sin abrir. Sin paginación.
     */
    public function exams(ListEligibleListExamsRequest $request): JsonResponse
    {
        $teacherId = $this->currentUser->teacherId();

        $exams = $this->service->examsForSelector($teacherId);

        return ApiResponse::success(
            EligibleListExamOptionResource::collection($exams)
        );
    }

    public function preview(
        PreviewEligibleListRequest $request,
        int $exam
    ): JsonResponse {
        $teacherId = $this->currentUser->teacherId();

        $file = $request->file('archivo');

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException(
                'No fue posible acceder temporalmente al archivo cargado.'
            );
        }

        $result = $this->service->preview(
            $exam,
            $path,
            $file->getClientOriginalExtension(),
            $teacherId
        );

        return ApiResponse::success(new EligibleListPreviewResource($result));
    }

    public function confirm(
        ConfirmEligibleListRequest $request,
        int $exam
    ): JsonResponse {
        $teacherId = $this->currentUser->teacherId();

        $result = $this->service->replaceFromUpload(
            $exam,
            (string) $request->validated()['token'],
            $teacherId
        );

        return ApiResponse::success(
            new EligibleListImportResource($result),
            'Lista de habilitados reemplazada correctamente.'
        );
    }

    public function current(ShowEligibleListRequest $request, int $exam): JsonResponse
    {
        $teacherId = $this->currentUser->teacherId();

        $result = $this->service->current($exam, $teacherId);

        return ApiResponse::success(new EligibleListCurrentResource($result));
    }
}
