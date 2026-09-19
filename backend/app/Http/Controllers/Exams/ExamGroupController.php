<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\AssignGroupsRequest;
use App\Models\Exam;
use App\Services\Exams\ExamGroupService;
use App\Http\Resources\Exams\ExamResource;
use App\Http\Resources\Exams\GroupResource;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API para la consulta y asignación de grupos académicos a los exámenes.
 */
class ExamGroupController extends Controller
{
    protected ExamGroupService $groupService;

    public function __construct(ExamGroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    public function assignGroups(AssignGroupsRequest $request, int $id): JsonResponse
    {
        try {
            $exam = Exam::findOrFail($id);
            $exam = $this->groupService->assignGroups($exam, $request->validated()['grupos']);

            return response()->json([
                'message' => 'Grupos asignados al examen exitosamente',
                'data'    => new ExamResource($exam->load(['classrooms', 'groups'])),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getGroupsBySubject(int $subjectId): JsonResponse
    {
        try {
            $groups = $this->groupService->getGroupsBySubject($subjectId);

            return response()->json([
                'message' => 'Grupos de la materia obtenidos exitosamente',
                'data'    => GroupResource::collection($groups),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
