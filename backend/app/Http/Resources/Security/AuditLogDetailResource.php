<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de una operación de la bitácora: la fila del listado más el registro afectado completo y los cambios
 * campo por campo. Los campos ocultos viajan como {campo, oculto: true}, sin valores.
 */
class AuditLogDetailResource extends JsonResource
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
            'registro' => $entry['registro'],
            'automatico' => $entry['automatico'],
            'docente_examen' => $entry['docente_examen'],
            'cambios' => $entry['cambios'],
        ];
    }
}
