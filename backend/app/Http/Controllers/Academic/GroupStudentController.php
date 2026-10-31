<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListGroupStudentsRequest;
use App\Http\Resources\Academic\GroupStudentResource;
use App\Services\Academic\GroupService;
use App\Services\Academic\GroupStudentService;
use App\Support\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Estudiantes inscritos en un grupo propio (HU-020).
 */
class GroupStudentController extends Controller
{
    private GroupService $groupService;

    private GroupStudentService $groupStudentService;

    private CurrentUser $currentUser;

    public function __construct(
        GroupService $groupService,
        GroupStudentService $groupStudentService,
        CurrentUser $currentUser
    ) {
        $this->groupService = $groupService;
        $this->groupStudentService = $groupStudentService;
        $this->currentUser = $currentUser;
    }

    /** GET /api/grupos/{id_grupo}/estudiantes */
    public function index(ListGroupStudentsRequest $request): AnonymousResourceCollection
    {
        $group = $this->groupService->findGroup($request->groupId());

        $teacherId = $this->currentUser->teacherId();

        $this->authorize('view', [$group, $teacherId]);

        $result = $this->groupStudentService->paginateForGroup(
            $group,
            $teacherId,
            $request->search(),
            $request->page()
        );

        return GroupStudentResource::collection($result['students'])
            ->additional(['meta' => ['total_inscritos' => $result['total_inscritos']]]);
    }
}
