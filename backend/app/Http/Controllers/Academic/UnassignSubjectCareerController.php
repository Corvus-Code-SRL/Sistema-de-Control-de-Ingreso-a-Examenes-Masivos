<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListUnassignableSubjectsRequest;
use App\Http\Requests\Academic\UnassignSubjectCareerRequest;
use App\Models\SubjectCareer;
use App\Services\Academic\SubjectCareerUnassignmentService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

class UnassignSubjectCareerController extends Controller
{
    private SubjectCareerUnassignmentService $service;
    private CurrentUser $currentUser;

    public function __construct(
        SubjectCareerUnassignmentService $service,
        CurrentUser $currentUser
    ) {
        $this->service = $service;
        $this->currentUser = $currentUser;
    }

    /** GET /api/catalogo/desvincular-materia — lista plana con conteo de grupos. */
    public function index(ListUnassignableSubjectsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SubjectCareer::class);

        $validated = $request->validated();

        $payload = $this->service->list(
            (int) $validated['carrera'],
            isset($validated['periodo']) ? (int) $validated['periodo'] : null,
            $validated['buscar'] ?? null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 7),
        );

        return response()->json($payload);
    }

    /** POST /api/catalogo/desvincular-materia — estado → INACTIVO. */
    public function unassign(UnassignSubjectCareerRequest $request): JsonResponse
    {
        $this->authorize('create', SubjectCareer::class);

        $validated = $request->validated();

        $oferta = $this->service->unassign(
            (int) $validated['id_carrera'],
            (int) $validated['id_periodo'],
            (int) $validated['id_materia'],
            (string) $this->currentUser->id()
        );

        return response()->json([
            'id_carrera' => (int) $oferta->id_carrera,
            'id_materia' => (int) $oferta->id_materia,
            'id_periodo' => (int) $oferta->id_gestion_periodo,
            'estado' => $oferta->estado,
            'grupos_count' => 0,
        ]);
    }
}
