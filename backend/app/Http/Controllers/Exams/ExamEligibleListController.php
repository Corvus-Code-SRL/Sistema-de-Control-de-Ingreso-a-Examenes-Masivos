<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ConfirmEligibleListRequest;
use App\Http\Requests\Exams\PreviewEligibleListRequest;
use App\Http\Requests\Exams\SaveEligibleListChangesRequest;
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
 * Camino principal:
 * - GET /habilitados → EligibleListCurrentResource
 * - PUT /habilitados → aplica cambios en transacción
 *
 * Camino secundario (importación):
 * - POST /habilitados/preview → preview del archivo
 * - POST /habilitados/confirm → devuelve cambios pendientes (no aplica)
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
     * Solo exámenes del docente en PROGRAMADO o EN_INGRESO. Sin paginación.
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

        $validated = $request->validated();

        $result = $this->service->confirm(
            $exam,
            (string) $validated['token'],
            $teacherId,
            (string) $validated['motivo'],
            isset($validated['detalle']) ? (string) $validated['detalle'] : null
        );

        return ApiResponse::success(new EligibleListImportResource($result));
    }

    public function save(
        SaveEligibleListChangesRequest $request,
        int $exam
    ): JsonResponse {
        $teacherId = $this->currentUser->teacherId();

        $validated = $request->validated();

        $this->service->saveChanges(
            $exam,
            $validated['cambios'],
            $teacherId
        );

        $result = $this->service->current($exam, $teacherId);

        return ApiResponse::success(
            new EligibleListCurrentResource($result),
            'Lista de habilitados guardada correctamente.'
        );
    }

    public function current(int $exam): JsonResponse
    {
        $teacherId = $this->currentUser->teacherId();

        $result = $this->service->current($exam, $teacherId);

        return ApiResponse::success(new EligibleListCurrentResource($result));
    }
}
