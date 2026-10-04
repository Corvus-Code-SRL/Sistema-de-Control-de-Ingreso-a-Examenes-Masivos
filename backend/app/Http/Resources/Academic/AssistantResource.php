<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Auxiliar expuesto en "Mis auxiliares".
 *
 * Solo lo necesario para identificarlo: nombre completo y código SIS.
 * Nunca expone contrasenia ni datos internos.
 */
class AssistantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_usuario' => (string) $this->id_usuario,
            'nombre' => $this->nombre,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'nombre_completo' => $this->nombre_completo,
            'cod_sis' => $this->cod_sis,
            'correo' => $this->correo,
        ];
    }
}