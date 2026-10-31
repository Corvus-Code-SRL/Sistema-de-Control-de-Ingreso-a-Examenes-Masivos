<?php

namespace App\Http\Controllers\EntryControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryControl\IndexEntryAttemptRequest;
use App\Http\Resources\EntryControl\EntryAttemptResource;
use App\Services\EntryControl\EntryAttemptQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EntryAttemptController extends Controller
{
    public function index(IndexEntryAttemptRequest $request, EntryAttemptQueryService $attempts): JsonResponse
    {
        $input = $request->validated();
        $records = $attempts->recent((int) $input['examen'], (int) $input['id_ambiente'], $request->user());

        return ApiResponse::success(EntryAttemptResource::collection($records));
    }
}
