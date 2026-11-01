<?php

namespace App\Http\Resources\EntryControl;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sugerencia de la búsqueda del ingreso con retraso (HU-037), con la misma forma que la de
 * HU-032: el CI solo viaja cuando se buscó por CI.
 */
class LateEntryStudentMatchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_estudiante' => $this->resource['id_estudiante'],
            'cod_sis' => $this->resource['cod_sis'],
            'nombre_completo' => $this->resource['nombre_completo'],
        ] + (array_key_exists('ci', $this->resource) ? ['ci' => $this->resource['ci']] : []);
    }
}
