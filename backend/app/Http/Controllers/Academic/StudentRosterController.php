<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ConfirmStudentRosterRequest;
use App\Http\Requests\Academic\PreviewStudentRosterRequest;
use App\Http\Resources\Academic\StudentRosterConfirmationResource;
use App\Http\Resources\Academic\StudentRosterPreviewResource;
use App\Services\Academic\Importers\StudentRosterConfirmationService;
use App\Services\Academic\Importers\StudentRosterPreviewService;
use RuntimeException;

class StudentRosterController extends Controller
{
    private StudentRosterPreviewService $previewService;

    private StudentRosterConfirmationService $confirmationService;

    public function __construct(
        StudentRosterPreviewService $previewService,
        StudentRosterConfirmationService $confirmationService
    ) {
        $this->previewService = $previewService;
        $this->confirmationService = $confirmationService;
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
            $file->getClientOriginalExtension()
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
            (string) $validated['token']
        );

        return new StudentRosterConfirmationResource(
            $result
        );
    }
}