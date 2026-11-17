<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estudiante encontrado por código SIS, antes de registrarlo como excepcional (HU-023).
 * Solo nombre, código SIS y carrera; el CI, el contacto y la foto no salen de aquí.
 */
class ExceptionalStudentCandidateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_estudiante' => (int) $this->id_estudiante,
            'cod_sis' => (string) $this->cod_sis,
            'nombre_completo' => (string) $this->nombre_completo,
            'carrera' => $this->carrera,
            'ya_en_examen' => (bool) $this->ya_en_examen,
        ];
    }
}
