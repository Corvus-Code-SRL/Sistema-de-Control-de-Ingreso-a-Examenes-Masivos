<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/** Un estudiante con sus registros confirmados, listos para elegir cuál rectificar (HU-062). */
class RectifiableStudentRecordsResource extends JsonResource
{
    /** @var iterable<\App\Models\RiskCenter> */
    private iterable $records;

    public function __construct($student, iterable $records)
    {
        parent::__construct($student);

        $this->records = $records;
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
        ];
    }
}
