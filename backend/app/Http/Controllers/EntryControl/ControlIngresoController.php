<?php

namespace App\Http\Controllers\EntryControl;

use App\Http\Controllers\Controller;
use App\Services\Exams\RedControlService; 
use App\Services\Exams\PresenciaControlService; 
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlIngresoController extends Controller
{
    private RedControlService $redControlService;
    private PresenciaControlService $presenciaService;

    public function __construct(
        RedControlService $redControlService,
        PresenciaControlService $presenciaService
    ) {
        $this->redControlService = $redControlService;
        $this->presenciaService = $presenciaService;
    }

    public function currentStatus(Request $request, int $examId): JsonResponse
    {
        //Registramos el latido del auxiliar logueado
        $this->presenciaService->registerHeartbeat(
            $examId, 
            $request->user()->id_usuario, 
            $request->user()->nombre
        );

        $clientVersion = $request->query('desde');
        $data = $this->redControlService->currentStatus($examId, $clientVersion);

        if (isset($data['sin_cambios'])) {
            return response()->json($data, 304);
        }

        //Inyectamos la lista de conectados antes de devolver el JSON
        $data['conectados'] = $this->presenciaService->getConnectedUsers($examId);

        return response()->json(['data' => $data], 200);
    }
}