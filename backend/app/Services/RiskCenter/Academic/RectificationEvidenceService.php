<?php

namespace App\Services\RiskCenter\Academic;

use App\Support\Storage\EvidenceStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Ubica los archivos de evidencia que se ven desde una solicitud de rectificación (HU-062): la del reporte
 * original (solo la ve el docente destinatario) y la imagen del rechazo (la ven el docente y el Administrador).
 */
class RectificationEvidenceService
{
    private RectificationRequestQueryService $requests;

    private EvidenceStorage $storage;

    public function __construct(RectificationRequestQueryService $requests, EvidenceStorage $storage)
    {
        $this->requests = $requests;
        $this->storage = $storage;
    }

    /**
     * @return array{disk: string, path: string}
     *
     * @throws ModelNotFoundException si no hay evidencia o el archivo no está en ningún disco
     */
    public function locateReportEvidence(int $requestId, string $teacherId): array
    {
        $request = $this->requests->findForTeacher($requestId, $teacherId);

        return $this->locate($request->record->studentReport->evidencia_public_id);
    }

    /**
     * Imagen del rechazo. Con `$teacherId` la pide el docente destinatario; sin él, el Administrador.
     *
     * @return array{disk: string, path: string}
     *
     * @throws ModelNotFoundException si no hay evidencia o el archivo no está en ningún disco
     */
    public function locateRejectionEvidence(int $requestId, ?string $teacherId = null): array
    {
        $request = $teacherId === null
            ? $this->requests->find($requestId)
            : $this->requests->findForTeacher($requestId, $teacherId);

        return $this->locate($request->evidencia_public_id);
    }

    private function locate(?string $path): array
    {
        $location = $this->storage->locate($path);

        if ($location === null) {
            throw new ModelNotFoundException('La solicitud no tiene una evidencia disponible.');
        }

        return $location;
    }
}
