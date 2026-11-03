<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila del listado de la bitácora. El recurso es el arreglo ya presentado por AuditLogQueryService, que
 * solo contiene los campos listados aquí: los valores crudos antiguo_valor/nuevo_valor nunca llegan a esta capa.
 */
class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        $entry = $this->resource;

        return [
            'id_log' => $entry['id_log'],
            'fecha_hora' => $entry['fecha_hora'],
            'usuario' => $entry['usuario'],
            'accion' => $entry['accion'],
            'modulo' => $entry['modulo'],
            'registro' => [
                'tabla' => $entry['registro']['tabla'],
                'etiqueta' => $entry['registro']['etiqueta'],
                'descripcion' => $entry['registro']['descripcion'],
            ],
            'automatico' => $entry['automatico'],
        ];
    }
}
