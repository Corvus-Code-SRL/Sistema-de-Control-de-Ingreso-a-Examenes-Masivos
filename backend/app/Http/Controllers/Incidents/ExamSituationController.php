<?php

namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\ListExamSituationsRequest;
use App\Http\Requests\Incidents\StoreExamSituationRequest;
use App\Http\Resources\Incidents\ExamSituationResource;
use App\Services\Incidents\ExamSituationQueryService;
use App\Services\Incidents\ExamSituationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ExamSituationController extends Controller
{
    public function store(StoreExamSituationRequest $request, ExamSituationService $situations): JsonResponse
    {
        $input = $request->validated();
        $situation = $situations->register(
            (int) $input['examen'],
            (int) $input['estudiante'],
            $input,
            $request->user()
        );

        return ApiResponse::created(new ExamSituationResource($situation), 'Situación registrada');
    }

    public function index(ListExamSituationsRequest $request, ExamSituationQueryService $situations): JsonResponse
    {
        $filters = $request->validated();
        $page = $situations->paginate((int) $filters['examen'], $filters, $request->user());

        return response()->json([
            'data' => ExamSituationResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}