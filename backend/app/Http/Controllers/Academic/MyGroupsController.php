<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ManageAssistantsRequest;
use App\Http\Resources\Academic\GroupOptionResource;
use App\Services\Academic\AssistantService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Grupos del docente en el período activo (HU-08).
 *
 * Lo consume el feature "Mis auxiliares" para poblar los selectores de
 * "Asignar auxiliar" y "Mover de grupo", que necesitan la lista completa
 * de grupos del docente, no solo donde el auxiliar ya está.
 */
class MyGroupsController extends Controller
{
    private AssistantService $assistantService;

    private CurrentUser $currentUser;

    public function __construct(AssistantService $assistantService, CurrentUser $currentUser)
    {
        $this->assistantService = $assistantService;
        $this->currentUser = $currentUser;
    }

    public function index(ManageAssistantsRequest $request): JsonResponse
    {
        $groups = $this->assistantService->listTeacherGroups($this->currentUser->teacherId());

        return ApiResponse::success(GroupOptionResource::collection($groups));
    }
}
