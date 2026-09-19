<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Services\Exams\ExamFormService;
use App\Http\Resources\Exams\SubjectResource;
use App\Http\Resources\Exams\ClassroomResource;
use App\Http\Resources\Exams\GroupResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Controlador API para proveer los datos iniciales de referencia de formularios de examen.
 */
class ExamFormController extends Controller
{
    protected ExamFormService $formService;

    public function __construct(ExamFormService $formService)
    {
        $this->formService = $formService;
    }

    public function getFormData(): JsonResponse
    {
        try {
            $teacherId = auth()->id() ?? DB::table('usuario')->where('estado', 'ACTIVO')->value('id_usuario');
            $data = $this->formService->getFormData($teacherId);

            return response()->json([
                'message' => 'Datos obtenidos exitosamente',
                'data'    => [
                    'materias'  => SubjectResource::collection($data['materias']),
                    'ambientes' => ClassroomResource::collection($data['ambientes']),
                    'grupos'    => GroupResource::collection($data['grupos']),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
