<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un estudiante con sus registros activos, listos para elegir cuál rectificar, y aparte los ya rectificados
 * (HU-062).
 */
class RectifiableStudentRecordsResource extends JsonResource
{
    /** @var iterable<\App\Models\RiskCenter> */
    private iterable $records;

    /** @var iterable<\App\Models\RiskCenter> */
    private iterable $rectified;

    public function __construct($student, iterable $records, iterable $rectified)
    {
        parent::__construct($student);

        $this->records = $records;
        $this->rectified = $rectified;
    }

    public function toArray($request): array
    {
        return [
            'estudiante' => [
                'id_estudiante' => (int) $this->id_estudiante,
                'nombre_completo' => $this->nombre_completo,
                'cod_sis' => $this->cod_sis,
                'ci' => $this->ci,
            ],
            'registros' => RectifiableRecordResource::collection($this->records),
            'rectificados' => RectifiedRecordResource::collection($this->rectified),
        ];
    }
}
