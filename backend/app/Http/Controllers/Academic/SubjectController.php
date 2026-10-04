<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\UpdateSubjectRequest;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Http\Resources\Academic\SubjectResource;
use App\Models\Subject;
use App\Services\Academic\SubjectCatalogService;
use App\Services\Academic\SubjectService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Expone el catálogo institucional de materias como pares materia-carrera
 * y gestiona el registro de nuevas materias.
 */
class SubjectController extends Controller
{
    private SubjectCatalogService $subjectCatalog;
    private SubjectService $subjectService;
    private CurrentUser $currentUser;

    // Se inyectan ambos servicios (el de tu compañero y el tuyo)
    public function __construct(
        SubjectCatalogService $subjectCatalog,
        SubjectService $subjectService,
        CurrentUser $currentUser
    ) {
        $this->subjectCatalog = $subjectCatalog;
        $this->subjectService = $subjectService;
        $this->currentUser = $currentUser;
    }

    // Método de tu compañero (no lo tocamos, queda intacto)
    public function index(): AnonymousResourceCollection
    {
        $result = $this->subjectCatalog->listSubjectCareers($this->currentUser->teacherId());

        $additional = ['meta' => $result['meta']];

        if ($result['mensaje'] !== null) {
            $additional['mensaje'] = $result['mensaje'];
        }

        return SubjectCareerResource::collection($result['pairs'])
            ->additional($additional);
    }

    public function adminIndex(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subject::class);

        return SubjectResource::collection(
            $this->subjectService->listForAdministration()
        );
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectService->create($request->validated(), $this->currentUser->id());

        return response()->json([
            'data'    => new SubjectResource($subject),
            'mensaje' => 'Materia registrada correctamente.'
        ], 201);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject = $this->subjectService->update(
            $subject,
            $request->validated(),
            $this->currentUser->id()
        );

        return response()->json([
            'data'    => new SubjectResource($subject),
            'mensaje' => 'Materia actualizada correctamente.',
        ]);
    }
}
