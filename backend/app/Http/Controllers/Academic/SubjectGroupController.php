<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ShowSubjectCareerGroupsRequest;
use App\Http\Resources\Academic\GroupResource;
use App\Http\Resources\Academic\SubjectCareerResource;
use App\Services\Academic\SubjectCatalogService;
use Illuminate\Http\JsonResponse;

/**
 * Expone los grupos de un par materia-carrera, nunca de una materia aislada.
 */
class SubjectGroupController extends Controller
{
    private SubjectCatalogService $subjectCatalog;

    public function __construct(SubjectCatalogService $subjectCatalog)
    {
        $this->subjectCatalog = $subjectCatalog;
    }

    public function index(ShowSubjectCareerGroupsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->subjectCatalog->listGroupsForPair(
            (int) $validated['id_carrera'],
            (int) $validated['id_materia']
        );

        return response()->json([
            'data' => [
                'materia' => new SubjectCareerResource($result['pair']),
                'grupos' => GroupResource::collection($result['groups']),
            ],
            'meta' => $result['meta'],
        ]);
    }
}
