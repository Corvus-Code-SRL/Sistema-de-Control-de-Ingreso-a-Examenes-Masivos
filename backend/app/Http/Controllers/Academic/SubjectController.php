<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListAdminSubjectsRequest;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Http\Resources\Academic\SubjectResource;
use App\Services\Academic\SubjectCatalogService;
use App\Services\Academic\SubjectService;
use App\Support\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Expone el catálogo institucional de materias: los pares materia-carrera del docente y el
 * catálogo de solo lectura de Administración. Las materias no se crean ni se editan desde la app.
 */
class SubjectController extends Controller
{
    private SubjectCatalogService $subjectCatalog;
    private SubjectService $subjectService;
    private CurrentUser $currentUser;

    public function __construct(
        SubjectCatalogService $subjectCatalog,
        SubjectService $subjectService,
        CurrentUser $currentUser
    ) {
        $this->subjectCatalog = $subjectCatalog;
        $this->subjectService = $subjectService;
        $this->currentUser = $currentUser;
    }

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

    public function adminIndex(ListAdminSubjectsRequest $request): AnonymousResourceCollection
    {
        return SubjectResource::collection(
            $this->subjectService->listForAdministration($request->validated()['q'] ?? null)
        );
    }
}
