<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AddAssistantToGroupRequest;
use App\Http\Requests\Academic\AddAssistantToGroupsRequest;
use App\Http\Requests\Academic\EnableAssistantForExamRequest;
use App\Http\Requests\Academic\ManageAssistantsRequest;
use App\Http\Requests\Academic\SearchAssistantRequest;
use App\Http\Requests\Academic\ShowGroupAssistantsRequest;
use App\Http\Resources\Academic\AssistantResource;
use App\Http\Resources\Academic\AssistantWithGroupsResource;
use App\Http\Resources\Academic\GroupAssistantResource;
use App\Services\Academic\AssistantService;
use App\Services\Academic\GroupService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Gestión de auxiliares del docente (HU-08).
 *
 * El Controller entrega el docente actual al Service, que verifica que el grupo o
 * examen le pertenezca. El rol se valida en cada Form Request.
 */
class AssistantController extends Controller
{
    private AssistantService $assistantService;

    private GroupService $groupService;

    private CurrentUser $currentUser;

    public function __construct(
        AssistantService $assistantService,
        GroupService $groupService,
        CurrentUser $currentUser
    ) {
        $this->assistantService = $assistantService;
        $this->groupService = $groupService;
        $this->currentUser = $currentUser;
    }

    public function index(ManageAssistantsRequest $request): JsonResponse
    {
        $assistants = $this->assistantService->listForTeacher($this->currentUser->teacherId());

        return ApiResponse::success(AssistantWithGroupsResource::collection($assistants));
    }

    /** GET /api/grupos/{id_grupo}/auxiliares — pestaña Auxiliares del curso (solo lectura). */
    public function forGroup(ShowGroupAssistantsRequest $request): JsonResponse
    {
        $group = $this->groupService->findGroup((int) $request->validated()['id_grupo']);

        $this->authorize('view', [$group, $this->currentUser->teacherId()]);

        return ApiResponse::success(GroupAssistantResource::collection(
            $this->assistantService->listForGroup($group)
        ));
    }

    public function search(SearchAssistantRequest $request): JsonResponse
    {
        $assistants = $this->assistantService->search(
            $request->validated()['criterio']
        );

        return ApiResponse::success(AssistantResource::collection($assistants));
    }

    public function addToGroup(AddAssistantToGroupRequest $request, int $id_grupo): JsonResponse
    {
        $this->assistantService->addToGroup(
            $id_grupo,
            $request->validated()['id_usuario'],
            $this->currentUser->teacherId()
        );

        return ApiResponse::created(null, 'Auxiliar incorporado al grupo correctamente.');
    }

    public function addToGroups(AddAssistantToGroupsRequest $request, string $id_usuario): JsonResponse
    {
        $this->assistantService->addToGroups(
            $id_usuario,
            $request->validated()['grupos'],
            $this->currentUser->teacherId()
        );

        return ApiResponse::created(null, 'Auxiliar incorporado a los grupos seleccionados.');
    }

    public function enableForExam(EnableAssistantForExamRequest $request, int $id_examen): JsonResponse
    {
        $this->assistantService->enableForExam(
            $id_examen,
            $request->validated()['id_usuario'],
            $this->currentUser->teacherId()
        );

        return ApiResponse::created(null, 'Auxiliar habilitado para el examen correctamente.');
    }

    public function removeFromGroup(ManageAssistantsRequest $request, int $id_grupo, string $id_usuario): JsonResponse
    {
        $this->assistantService->removeFromGroup($id_grupo, $id_usuario, $this->currentUser->teacherId());

        return ApiResponse::success(null, 'Auxiliar quitado del grupo correctamente.');
    }

    public function removeFromExam(ManageAssistantsRequest $request, int $id_examen, string $id_usuario): JsonResponse
    {
        $this->assistantService->removeFromExam($id_examen, $id_usuario, $this->currentUser->teacherId());

        return ApiResponse::success(null, 'Auxiliar quitado del examen correctamente.');
    }
}
