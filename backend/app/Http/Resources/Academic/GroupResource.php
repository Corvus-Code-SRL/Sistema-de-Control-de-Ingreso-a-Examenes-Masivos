<?php

namespace App\Http\Resources\Academic;

use App\Support\RecordStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_grupo' => (int) $this->id_grupo,
            'num_grupo' => $this->num_grupo,
            'gestion' => $this->gestion,
            'activo' => $this->estado === RecordStatus::ACTIVE,
            'es_mio' => (bool) $this->es_mio,
            'cantidad_estudiantes' => (int) $this->cantidad_estudiantes,
            'docente' => [
                'nombre_completo' => implode(' ', array_filter([
                    $this->docente_nombre,
                    $this->docente_apellido_paterno,
                    $this->docente_apellido_materno,
                ])),
            ],
            'periodo' => [
                'id_periodo' => (int) $this->period->id_periodo,
                'nombre_periodo' => $this->period->nombre_periodo,
                'gestion' => (int) $this->period->gestion,
            ],
        ];
    }
}
