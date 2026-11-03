<?php

namespace App\Http\Resources\Incidents;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamSituationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_situacion' => (int) $this->id_situacion,
            'tipo' => $this->tipo,
            'motivo' => $this->motivo,
            'registrado_en' => $this->registrado_en->copy()->utc()->toIso8601String(),
            'estudiante' => [
                'id_estudiante' => (int) $this->student->id_estudiante,
                'cod_sis' => $this->student->cod_sis,
                'nombre_completo' => trim(implode(' ', array_filter([
                    $this->student->nombre,
                    $this->student->apellido_paterno,
                    $this->student->apellido_materno,
                ]))),
            ],
            'ambiente_origen' => [
                'id_ambiente' => (int) $this->originRoom->id_ambiente,
                'nro_aula' => $this->originRoom->nro_aula,
            ],
            'ambiente_destino' => $this->destinationRoom === null ? null : [
                'id_ambiente' => (int) $this->destinationRoom->id_ambiente,
                'nro_aula' => $this->destinationRoom->nro_aula,
            ],
            'registrante' => [
                'id_usuario' => $this->reporter->id_usuario,
                'nombre_completo' => $this->reporter->nombre_completo,
            ],
        ];
    }
}