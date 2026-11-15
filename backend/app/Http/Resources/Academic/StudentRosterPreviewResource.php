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
            // HU-021 multicarrera: carreras del grupo, para mostrar y filtrar la carrera de cada fila.
            'carreras_grupo' => array_map(
                static function (array $career): array {
                    return [
                        'id_carrera' => $career['id_carrera'],
                        'codigo' => $career['codigo'],
                        'nombre' => $career['nombre'],
                        'principal' => $career['principal'],
                    ];
                },
                $this->resource->groupCareers()
            ),
            'filas' => array_map(
                static function (array $row): array {
                    return [
                        'numero_fila' => $row['row_number'],
                        'codigo_sis' => $row['sis_code'],
                        'apellidos' => $row['last_names'],
                        'nombres' => $row['first_names'],
                        'carrera_archivo' => $row['career_text'] ?? null,
                        'carrera' => $row['career'] ?? null,
                        'estado' => $row['status'],
                        'errores' => $row['errors'],
                    ];
                },
                $this->resource->rows()
            ),
        ];
    }
}
