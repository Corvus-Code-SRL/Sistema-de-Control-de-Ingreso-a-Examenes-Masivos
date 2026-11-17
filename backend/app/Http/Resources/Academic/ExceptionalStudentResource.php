<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estudiante recién registrado como excepcional en un examen (HU-023).
 */
class ExceptionalStudentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen' => (int) $this->id_examen,
            'id_estudiante' => (int) $this->id_estudiante,
            'cod_sis' => (string) $this->cod_sis,
            'nombre_completo' => (string) $this->nombre_completo,
            'carrera' => $this->carrera,
            'motivo_excepcional' => (string) $this->motivo_excepcional,
            'fecha_registro' => (string) $this->fecha_registro,
        ];
    }
}
