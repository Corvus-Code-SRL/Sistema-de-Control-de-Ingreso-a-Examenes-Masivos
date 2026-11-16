<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Docente que se puede invitar a un examen (HU-026). `ya_invitado`: tiene una invitación pendiente o
 * aceptada a ese examen.
 */
class CollaboratorCandidateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_usuario' => (string) $this->id_usuario,
            'nombre_completo' => $this->nombre_completo,
            'cod_sis' => $this->cod_sis,
            'ya_invitado' => (bool) $this->getAttribute('ya_invitado'),
        ];
    }
}
