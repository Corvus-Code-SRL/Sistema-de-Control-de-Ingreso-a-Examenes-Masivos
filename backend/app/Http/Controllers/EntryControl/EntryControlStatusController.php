<?php

namespace App\Http\Controllers\EntryControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryControl\EntryStatusRequest;
use App\Http\Resources\EntryControl\EntryStatusResource;
use App\Models\Exam;
use App\Services\EntryControl\EntryAccessService;
use App\Services\EntryControl\CloseOrdinaryEntryService;
use App\Services\Exams\RedControlService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntryControlStatusController extends Controller
{
    private RedControlService $redControlService;
    private EntryAccessService $access;
    private CloseOrdinaryEntryService $closure;

    public function __construct(RedControlService $redControlService, EntryAccessService $access, CloseOrdinaryEntryService $closure)
    {
        $this->redControlService = $redControlService;
        $this->access = $access;
        $this->closure = $closure;
    }

    public function openExams(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->access->openExamsFor($request->user())]);
    }

    public function context(Request $request, Exam $examen): JsonResponse
    {
        return response()->json(['data' => $this->access->context($examen, $request->user())]);
    }

    public function closeOrdinary(Request $request, Exam $examen): JsonResponse
    {
        $this->closure->close($examen, $request->user());

        return ApiResponse::success(['ingreso_ordinario_cerrado' => true]);
    }

    public function currentStatus(EntryStatusRequest $request, $examen): JsonResponse
    {
        $data = $this->redControlService->currentStatus(
            (int) $examen,
            $request->user(),
            $request->validated()['desde'] ?? null
        );

        return ApiResponse::success(new EntryStatusResource($data));
    }
}
