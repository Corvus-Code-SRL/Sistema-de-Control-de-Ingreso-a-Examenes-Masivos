<?php

namespace App\Services\Academic\Importers;

use App\Exceptions\Academic\StudentRosterPreviewUnavailableException;
use App\Services\Academic\GroupCareerService;
use App\Services\Academic\StudentRosterGroupAccess;
use App\Services\Security\AuditLogService;
use Illuminate\Support\Facades\DB;

class StudentRosterConfirmationService
{
    private StudentRosterPreviewStore $previewStore;

    private StudentRosterGroupAccess $groupAccess;

    private StudentRosterAnalyzer $analyzer;

    private AuditLogService $auditLog;

    private StudentRosterConfirmer $confirmer;

    private GroupCareerService $groupCareers;

    private StudentRosterCareerResolver $careerResolver;

    public function __construct(
        StudentRosterPreviewStore $previewStore,
        StudentRosterGroupAccess $groupAccess,
        StudentRosterAnalyzer $analyzer,
        StudentRosterConfirmer $confirmer,
        AuditLogService $auditLog,
        GroupCareerService $groupCareers,
        StudentRosterCareerResolver $careerResolver
    ) {
        $this->previewStore = $previewStore;
        $this->groupAccess = $groupAccess;
        $this->analyzer = $analyzer;
        $this->confirmer = $confirmer;
        $this->auditLog = $auditLog;
        $this->groupCareers = $groupCareers;
        $this->careerResolver = $careerResolver;
    }

    public function confirm(
        int $groupId,
        string $token,
        string $teacherId
    ): StudentRosterConfirmationResult {
        $preview = $this->previewStore->find($token);

        if ($preview === null) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview de la nómina no existe o ha expirado.',
                404
            );
        }

        if ($preview->teacherId() !== $teacherId) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview no pertenece al docente actual.',
                403
            );
        }

        if ($preview->groupId() !== $groupId) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview no corresponde al grupo indicado.',
                422
            );
        }

        $group = $this->groupAccess->getAvailable(
            $groupId,
            $teacherId
        );

        // La carrera se resuelve otra vez: el grupo pudo dejar de atender una carrera desde el preview.
        $analysis = $this->careerResolver->resolve(
            $this->analyzer->analyze($preview->rows()),
            $this->groupCareers->careersOf($group),
            $preview->defaultCareerId()
        );

        $result = DB::transaction(function () use ($group, $analysis, $teacherId) {
            $result = $this->confirmer->confirm(
                (int) $group->id_grupo,
                $analysis
            );

            $this->auditLog->registrar(
                'CREAR',
                'grupo_estudiante',
                null,
                [
                    'id_grupo' => (int) $group->id_grupo,
                    'total_filas' => $result->totalRows(),
                    'estudiantes_creados' => $result->createdStudents(),
                    'estudiantes_inscritos' => $result->enrolledStudents(),
                    'estudiantes_omitidos' => $result->alreadyEnrolled(),
                    'filas_inconsistentes' => $result->inconsistentRows(),
                ],
                $teacherId
            );

            return $result;
        });

        $this->previewStore->forget($token);

        return $result;
    }
}
