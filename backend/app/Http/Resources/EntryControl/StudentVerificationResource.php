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
            'auxiliar' => $this['auxiliar'],
            'grupo' => $this['grupo'],
            'es_excepcional' => $this['es_excepcional'] ?? false,
            'motivo_excepcional' => $this['motivo_excepcional'] ?? null,
            'ambiente_asignado' => $this['ambiente_asignado'],
            'avisos_ambiente' => $this['avisos_ambiente'] ?? [],
            'antecedentes' => $this['antecedentes'],
            'ingreso_previo' => $this['ingreso_previo'],
            'reporte_pendiente' => $this['reporte_pendiente'] ?? false,
        ];
    }
}
