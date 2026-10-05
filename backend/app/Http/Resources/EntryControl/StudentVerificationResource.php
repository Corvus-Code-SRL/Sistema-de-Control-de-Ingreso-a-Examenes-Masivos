<?php

namespace App\Http\Resources\EntryControl;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentVerificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
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
