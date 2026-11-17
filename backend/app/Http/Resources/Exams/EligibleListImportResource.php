<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EligibleListImportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'total_filas' => $this->resource->totalRows(),
            'filas_inconsistentes' => $this->resource->inconsistentRows(),
            'no_encontrados' => $this->resource->noEncontrados(),
            'cambios_pendientes' => array_map(
                static function (array $cambio): array {
                    return [
                        'id_estudiante' => $cambio['id_estudiante'],
                        'codigo_sis' => $cambio['codigo_sis'],
                        'nombre_completo' => $cambio['nombre_completo'],
                        'estado_habilitacion' => $cambio['estado_habilitacion'],
                        'motivo' => $cambio['motivo'],
                        'detalle' => $cambio['detalle'],
                    ];
                },
                $this->resource->cambiosPendientes()
            ),
        ];
    }
}
