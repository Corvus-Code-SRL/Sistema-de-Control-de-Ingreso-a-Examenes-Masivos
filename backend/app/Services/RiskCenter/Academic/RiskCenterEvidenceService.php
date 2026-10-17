<?php

namespace App\Services\RiskCenter\Academic;

use App\Support\Storage\EvidenceStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Ubica el archivo de evidencia de un registro de la Central de Riesgo. */
class RiskCenterEvidenceService
{
    private RiskCenterQueryService $records;

    private EvidenceStorage $storage;

    public function __construct(RiskCenterQueryService $records, EvidenceStorage $storage)
    {
        $this->records = $records;
        $this->storage = $storage;
    }

    /**
     * Disco y ruta del archivo de la evidencia.
     *
     * @return array{disk: string, path: string}
     *
     * @throws ModelNotFoundException si el registro no existe, no tiene evidencia, la ruta guardada sale de
     *         evidencias/ o el archivo no está en ningún disco
     */
    public function locate(int $id): array
    {
        $record = $this->records->find($id);

        $location = $this->storage->locate($record->studentReport->evidencia_public_id);

        if ($location === null) {
            throw new ModelNotFoundException('El registro no tiene una evidencia disponible.');
        }

        return $location;
    }
}
