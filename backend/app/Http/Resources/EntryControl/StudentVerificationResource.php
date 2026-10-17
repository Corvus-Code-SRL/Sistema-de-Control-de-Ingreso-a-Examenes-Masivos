<?php

namespace App\Http\Resources\EntryControl;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentVerificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_intento' => $this['id_intento'] ?? null,
            'registrado_en' => $this['registrado_en'] ?? null,
            'reportado' => false,
            'veredicto' => $this['veredicto'],
            'motivo' => $this['motivo'],
            'autorizado' => $this['autorizado'],
            'estudiante' => $this['estudiante'],
            'grupo' => $this['grupo'],
            'ambiente_asignado' => $this['ambiente_asignado'],
            'antecedentes' => $this['antecedentes'],
            'ingreso_previo' => $this['ingreso_previo'],
        ];
    }
}
