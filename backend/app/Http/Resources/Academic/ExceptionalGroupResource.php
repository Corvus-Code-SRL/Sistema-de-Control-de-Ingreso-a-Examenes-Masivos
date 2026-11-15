<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Grupo del docente como opción para registrar un estudiante excepcional (HU-023).
 * `bloqueado` avisa de antemano que hay un examen en ingreso o en curso.
 */
class ExceptionalGroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_grupo' => (int) $this->id_grupo,
            'num_grupo' => (string) $this->num_grupo,
            'label' => (string) $this->label,
            'bloqueado' => (bool) $this->bloqueado,
            'carreras' => array_map(static fn (array $career): array => [
                'id_carrera' => (int) $career['id_carrera'],
                'nombre' => (string) $career['nombre'],
                'principal' => (bool) $career['principal'],
            ], $this->carreras),
        ];
    }
}
