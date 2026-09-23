<?php

namespace App\Services\Academic\Importers;

use App\Exceptions\Academic\StudentRosterPreviewUnavailableException;
use App\Services\Academic\StudentRosterGroupAccess;

class StudentRosterConfirmationService
{
    private StudentRosterPreviewStore $previewStore;

    private StudentRosterGroupAccess $groupAccess;

    private StudentRosterAnalyzer $analyzer;

    private StudentRosterConfirmer $confirmer;

    public function __construct(
        StudentRosterPreviewStore $previewStore,
        StudentRosterGroupAccess $groupAccess,
        StudentRosterAnalyzer $analyzer,
        StudentRosterConfirmer $confirmer
    ) {
        $this->previewStore = $previewStore;
        $this->groupAccess = $groupAccess;
        $this->analyzer = $analyzer;
        $this->confirmer = $confirmer;
    }

    public function confirm(
        int $groupId,
        string $token
    ): StudentRosterConfirmationResult {
        $preview = $this->previewStore->find($token);

        if ($preview === null) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview de la nómina no existe o ha expirado.',
                404
            );
        }

        $currentTeacherId = (string) config(
            'sciem.docente_fijo_id'
        );

        if ($preview->teacherId() !== $currentTeacherId) {
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
            $groupId
        );

        $analysis = $this->analyzer->analyze(
            $preview->rows()
        );

        $result = $this->confirmer->confirm(
            (int) $group->id_grupo,
            $analysis
        );

        $this->previewStore->forget($token);

        return $result;
    }

    /**
     * Confirma un preview standalone contra un grupo recién creado.
     *
     * El preview se generó sin id_grupo, así que no se validó contra
     * un grupo existente. Al confirmar, el grupo ya existe y se
     * reclasifica con el matcher normal.
     */
    public function confirmForNewGroup(
        int $groupId,
        string $token
    ): StudentRosterConfirmationResult {
        $preview = $this->previewStore->find($token);

        if ($preview === null) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview de la nómina no existe o ha expirado.',
                404
            );
        }

        $currentTeacherId = (string) config('sciem.docente_fijo_id');

        if ($preview->teacherId() !== $currentTeacherId) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview no pertenece al docente actual.',
                403
            );
        }

        // El preview standalone NO tiene grupo asignado.
        if ($preview->groupId() !== null) {
            throw new StudentRosterPreviewUnavailableException(
                'El preview ya está asociado a un grupo.',
                422
            );
        }

        // Reclasificar con el id_grupo real.
        $analysis = $this->analyzer->analyze(
            $preview->rows()
        );

        $result = $this->confirmer->confirm(
            $groupId,
            $analysis
        );

        $this->previewStore->forget($token);

        return $result;
    }
}