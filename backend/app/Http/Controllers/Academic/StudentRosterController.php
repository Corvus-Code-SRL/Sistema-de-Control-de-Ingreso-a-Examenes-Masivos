<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ConfirmStudentRosterRequest;
use App\Http\Requests\Academic\PreviewStudentRosterRequest;
use App\Http\Resources\Academic\StudentRosterConfirmationResource;
use App\Http\Resources\Academic\StudentRosterPreviewResource;
use App\Services\Academic\GroupService;
use App\Services\Academic\Importers\StudentRosterConfirmationService;
use App\Services\Academic\Importers\StudentRosterPreviewService;
use App\Support\CurrentUser;
use RuntimeException;

/**
 * Carga de nómina en dos pasos: preview (no escribe) y confirmación (único paso que escribe).
 */
class StudentRosterController extends Controller
{
    private StudentRosterPreviewService $previewService;

    private StudentRosterConfirmationService $confirmationService;

    private GroupService $groupService;

    private CurrentUser $currentUser;

    public function __construct(
        StudentRosterPreviewService $previewService,
        StudentRosterConfirmationService $confirmationService,
        GroupService $groupService,
        CurrentUser $currentUser
    ) {
        $this->previewService = $previewService;
        $this->confirmationService = $confirmationService;
        $this->groupService = $groupService;
        $this->currentUser = $currentUser;
    }

    public function preview(
        PreviewStudentRosterRequest $request,
        int $id_grupo
    ): StudentRosterPreviewResource {
        $teacherId = $this->authorizeRoster($id_grupo);

        $file = $request->file('archivo');

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException(
                'No fue posible acceder temporalmente al archivo cargado.'
            );
        }

        $result = $this->previewService->generate(
            $id_grupo,
            $path,
            $file->getClientOriginalExtension(),
            $teacherId
        );

        return new StudentRosterPreviewResource(
            $result
        );
    }

    public function confirm(
        ConfirmStudentRosterRequest $request,
        int $id_grupo
    ): StudentRosterConfirmationResource {
        $teacherId = $this->authorizeRoster($id_grupo);

        $validated = $request->validated();

        $result = $this->confirmationService->confirm(
            $id_grupo,
            (string) $validated['token'],
            $teacherId
        );

        return new StudentRosterConfirmationResource(
            $result
        );
    }

    /**
     * El grupo debe existir (404) y ser del docente que actúa (403). Devuelve ese docente
     * para pasarlo al Service como argumento.
     */
    private function authorizeRoster(int $groupId): string
    {
        $group = $this->groupService->findGroup($groupId);

        $teacherId = $this->currentUser->teacherId();

        $this->authorize('manageRoster', [$group, $teacherId]);

        return $teacherId;
    }
}
