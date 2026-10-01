<?php

namespace App\Http\Controllers\EntryControl;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\EntryControl\EntryAccessService;
use App\Services\Exams\RedControlService;
use App\Services\Exams\PresenciaControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntryControlStatusController extends Controller
{
    public function openExams(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->access->openExamsFor($request->user())]);
    }

    public function context(Request $request, Exam $examen): JsonResponse
    {
        return response()->json(['data' => $this->access->context($examen, $request->user())]);
    }

    private RedControlService $redControlService;
    private PresenciaControlService $presenciaService;
    private EntryAccessService $access;

    public function __construct(
        RedControlService $redControlService,
        PresenciaControlService $presenciaService,
        EntryAccessService $access
    ) {
        $this->redControlService = $redControlService;
        $this->presenciaService = $presenciaService;
        $this->access = $access;
    }

    public function currentStatus(Request $request, $examen): JsonResponse
    {
        $examId = (int) $examen;

        // Actualiza la presencia del controlador autenticado.
        $this->presenciaService->registerHeartbeat(
            $examId,
            $request->user()->id_usuario,
            $request->user()->nombre
        );

        $clientVersion = $request->query('desde');
        $data = $this->redControlService->currentStatus(
            $examId,
            $request->user(),
            is_string($clientVersion) ? $clientVersion : null
        );

        // El cliente HTTP consume siempre la misma envoltura, incluso si no hubo cambios.
        $data['conectados'] = $this->presenciaService->getConnectedUsers($examId);

        return response()->json(['data' => $data], 200);
    }
}
