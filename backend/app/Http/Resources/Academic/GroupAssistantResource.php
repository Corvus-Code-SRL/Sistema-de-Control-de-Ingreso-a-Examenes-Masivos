<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Auxiliar incorporado a un grupo, con los exámenes de ese grupo donde está habilitado.
 *
 * Envuelve el arreglo que arma AssistantService::listForGroup; aquí solo se fija la forma de la
 * respuesta.
 */
class GroupAssistantResource extends JsonResource
{
    public function toArray($request): array
    {
        $assistant = $this->resource;

        return [
            'id_usuario' => (string) $assistant['id_usuario'],
            'nombre_completo' => $assistant['nombre_completo'],
            'cod_sis' => $assistant['cod_sis'],
            'correo' => $assistant['correo'],
            'fecha_incorporacion' => $assistant['fecha_incorporacion'],
            'examenes' => $assistant['examenes'],
        ];
    }
}
