<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentRosterPreviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->resource->token(),
            'total_filas' => $this->resource->totalRows(),
            'filas_validas' => $this->resource->validRows(),
            'filas_inconsistentes' => $this->resource->inconsistentRows(),
            'filas' => array_map(
                static function (array $row): array {
                    return [
                        'numero_fila' => $row['row_number'],
                        'codigo_sis' => $row['sis_code'],
                        'apellidos' => $row['last_names'],
                        'nombres' => $row['first_names'],
                        'estado' => $row['status'],
                        'errores' => $row['errors'],
                    ];
                },
                $this->resource->rows()
            ),
        ];
    }
}