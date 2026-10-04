<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ConfirmStudentRosterRequest;
use App\Http\Requests\Academic\PreviewStudentRosterRequest;
use App\Http\Resources\Academic\StudentRosterConfirmationResource;
use App\Http\Resources\Academic\StudentRosterPreviewResource;
use App\Services\Academic\Importers\StudentRosterConfirmationService;
use App\Services\Academic\Importers\StudentRosterPreviewService;
use App\Support\CurrentUser;
use RuntimeException;

class StudentRosterController extends Controller
{
    private StudentRosterPreviewService $previewService;

    private StudentRosterConfirmationService $confirmationService;

    private CurrentUser $currentUser;

    public function __construct(
        StudentRosterPreviewService $previewService,
        StudentRosterConfirmationService $confirmationService,
        CurrentUser $currentUser
    ) {
        $this->previewService = $previewService;
        $this->confirmationService = $confirmationService;
        $this->currentUser = $currentUser;
    }

    public function preview(
        PreviewStudentRosterRequest $request,
        int $id_grupo
    ): StudentRosterPreviewResource {
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
            $this->currentUser->teacherId()
        );

        return new StudentRosterPreviewResource(
            $result
        );
    }

    public function confirm(
        ConfirmStudentRosterRequest $request,
        int $id_grupo
    ): StudentRosterConfirmationResource {
        $validated = $request->validated();

        $result = $this->confirmationService->confirm(
            $id_grupo,
            (string) $validated['token'],
            $this->currentUser->teacherId()
        );

        return new StudentRosterConfirmationResource(
            $result
        );
    }
}