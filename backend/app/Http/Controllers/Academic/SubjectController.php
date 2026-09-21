<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Services\Academic\SubjectCatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Expone el catálogo institucional de materias como pares materia-carrera.
 */
class SubjectController extends Controller
{
    private SubjectCatalogService $subjectCatalog;

    public function __construct(SubjectCatalogService $subjectCatalog)
    {
        $this->subjectCatalog = $subjectCatalog;
    }

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
}
