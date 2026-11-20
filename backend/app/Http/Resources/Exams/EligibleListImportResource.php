<?php

namespace App\Http\Resources\Exams;

use App\Support\MotivoNoHabilitacion;
use Illuminate\Http\Resources\Json\JsonResource;

class EligibleListImportResource extends JsonResource
{
    public function toArray($request): array
    {
        $noHabilitados = $this->resource->noHabilitados();

        return [
            'total_filas' => $this->resource->totalRows(),
            'filas_inconsistentes' => $this->resource->inconsistentRows(),
            'no_encontrados' => $this->resource->noEncontrados(),
            'habilitados' => $this->resource->habilitados(),
            'total_no_habilitados' => count($noHabilitados),
            'motivo' => MotivoNoHabilitacion::NO_FIGURA_EN_LISTA,
            'no_habilitados' => array_map(
                static fn (array $student): array => [
                    'id_estudiante' => $student['id_estudiante'],
                    'codigo_sis' => $student['codigo_sis'],
                    'nombre_completo' => $student['nombre_completo'],
                ],
                $noHabilitados
            ),
        ];
    }
}
