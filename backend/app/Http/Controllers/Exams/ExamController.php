<?php
namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CreateExamRequest;
use App\Services\Exams\ExamService;
use App\Http\Resources\Exams\ExamResource;
use App\Http\Resources\Exams\SubjectResource;
use App\Http\Resources\Exams\ClassroomResource;
use App\Http\Resources\Exams\GroupResource;
use Illuminate\Http\JsonResponse;

class ExamController extends Controller
{
    
    protected $examService;

    public function __construct(ExamService $examService) {
        $this->examService = $examService;
    }

    public function crear(CreateExamRequest $request): JsonResponse {
        try {
            //Ejecutar logica de negocio y guardar en la BD
            $exam = $this->examService->crearExamen($request->validated());

            //Retornar la respuesta JSON con codigo de estado 201
            return response()->json([
                'message' => 'Examen creado exitosamente',
                'data'    => new ExamResource($exam->load('ambiente')),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([   
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function obtenerDatosFormulario(): JsonResponse{
        try {
            $datos = $this->examService->obtenerDatosFormulario();
            return response()->json([   
                'message' => 'Datos obtenidos exitosamente',
                'data'    => [
                    'materias' => SubjectResource::collection($datos['materias']),
                    'ambientes' => ClassroomResource::collection($datos['ambientes']),
                    'grupos'=> GroupResource::collection($datos['grupos']),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([   
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
}