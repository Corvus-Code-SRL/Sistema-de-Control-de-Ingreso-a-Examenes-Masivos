<?php

namespace App\Http\Controllers\EntryControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryControl\SearchStudentRequest;
use App\Http\Requests\EntryControl\VerifyStudentRequest;
use App\Http\Resources\EntryControl\StudentVerificationResource;
use App\Services\EntryControl\EntryAttemptService;
use App\Services\EntryControl\IdentificationService;
use App\Services\EntryControl\VerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudentVerificationController extends Controller
{
    public function search(
        SearchStudentRequest $request,
        $examen,
        IdentificationService $identification
    ): JsonResponse {
        return ApiResponse::success($identification->searchByName(
            (int) $examen,
            $request->user(),
            $request->validated()['nombre']
        ));
    }

    public function verify(
        VerifyStudentRequest $request,
        $examen,
        VerificationService $verification,
        EntryAttemptService $attempts
    ): JsonResponse {
        $input = $request->validated();
        $verdict = $verification->verify((int) $examen, $input, $request->user());
        $attempts->recordDenied((int) $examen, $input, $request->user(), $verdict);

        return ApiResponse::success(new StudentVerificationResource($verdict));
    }
}
