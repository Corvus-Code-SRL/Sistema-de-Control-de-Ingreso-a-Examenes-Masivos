<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Http\Resources\Academic\SubjectResource;
use App\Services\Academic\SubjectCatalogService;
use App\Services\Academic\SubjectService;
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

    // Se inyectan ambos servicios (el de tu compañero y el tuyo)
    public function __construct(SubjectCatalogService $subjectCatalog, SubjectService $subjectService)
    {
        $this->subjectCatalog = $subjectCatalog;
        $this->subjectService = $subjectService;
    }

    // Método de tu compañero (no lo tocamos, queda intacto)
    public function index(): AnonymousResourceCollection
    {
        $result = $this->subjectCatalog->listSubjectCareers();

        $additional = ['meta' => $result['meta']];

        if ($result['mensaje'] !== null) {
            $additional['mensaje'] = $result['mensaje'];
        }

        return SubjectCareerResource::collection($result['pairs'])
            ->additional($additional);
    }

    // Tu nuevo método de la HU-006 para registrar la materia
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectService->create($request->validated());

        return response()->json([
            'data'    => new SubjectResource($subject),
            'mensaje' => 'Materia registrada correctamente.'
        ], 201);
    }
}