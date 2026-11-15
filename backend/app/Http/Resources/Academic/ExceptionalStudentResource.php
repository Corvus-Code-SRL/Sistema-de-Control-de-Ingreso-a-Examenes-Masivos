<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estudiante recién registrado como excepcional en un grupo (HU-023).
 */
class ExceptionalStudentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_grupo' => (int) $this->id_grupo,
            'id_estudiante' => (int) $this->id_estudiante,
            'cod_sis' => (string) $this->cod_sis,
            'nombre_completo' => (string) $this->nombre_completo,
            'carrera' => $this->carrera,
            'es_excepcional' => (bool) $this->es_excepcional,
            'motivo_excepcional' => (string) $this->motivo_excepcional,
            'fecha_inscripcion' => (string) $this->fecha_inscripcion,
        ];
    }
}
