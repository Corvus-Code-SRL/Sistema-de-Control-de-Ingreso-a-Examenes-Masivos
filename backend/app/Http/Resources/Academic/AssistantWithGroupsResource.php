<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Auxiliar de "Mis auxiliares" con sus grupos, sus exámenes habilitados y los exámenes
 * donde todavía puede habilitarse.
 *
 * Envuelve el arreglo que arma AssistantService::listForTeacher: ahí se decide qué grupos
 * y exámenes son del docente; aquí solo se fija la forma de la respuesta.
 */
class AssistantWithGroupsResource extends JsonResource
{
    public function toArray($request): array
    {
        $assistant = $this->resource;

        return [
            'id_usuario' => (string) $assistant['id_usuario'],
            'nombre' => $assistant['nombre'],
            'apellido_paterno' => $assistant['apellido_paterno'],
            'apellido_materno' => $assistant['apellido_materno'],
            'nombre_completo' => $assistant['nombre_completo'],
            'cod_sis' => $assistant['cod_sis'],
            'correo' => $assistant['correo'],
            'grupos' => $assistant['grupos'],
            'examenes' => $assistant['examenes'],
            'examenes_disponibles' => $assistant['examenes_disponibles'],
        ];
    }
}
