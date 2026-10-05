<?php

namespace App\Http\Controllers\EntryControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryControl\ConfirmEntryRequest;
use App\Http\Requests\EntryControl\RejectEntryRequest;
use App\Models\Exam;
use App\Services\EntryControl\EntryAttemptService;
use App\Services\EntryControl\EntryConfirmationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudentEntryController extends Controller
{
    public function confirm(
        ConfirmEntryRequest $request,
        Exam $examen,
        EntryConfirmationService $entries
    ): JsonResponse {
        $result = $entries->confirm(
            $examen,
            $request->validated(),
            $request->user()
        );

        if (! $result['creado']) {
            return ApiResponse::error(
                $result['motivo'],
                $result['veredicto'] === 'DUPLICADO' ? 409 : 422,
                ['veredicto' => $result['veredicto'], 'ingreso_previo' => $result['ingreso_previo'] ?? null]
            );
        }

        return ApiResponse::created($result, 'Ingreso registrado.');
    }

    public function reject(
        RejectEntryRequest $request,
        Exam $examen,
        EntryAttemptService $attempts
    ): JsonResponse {
        $result = $attempts->reject(
            $examen,
            $request->validated(),
            $request->user()
        );

        if (! $result['registrado']) {
            return ApiResponse::error($result['motivo'], 409, ['veredicto' => $result['veredicto']]);
        }

        return ApiResponse::created($result, 'Intento rechazado registrado.');
    }
}
