<?php

namespace App\Http\Resources\EntryControl;

use Illuminate\Http\Resources\Json\JsonResource;

class EntryAttemptResource extends JsonResource
{
    public function toArray($request): array
    {
        $attempt = $this['attempt'];
        $student = $attempt->student;

        return [
            'id_intento' => (int) $attempt->id_intento,
            'cod_sis' => $attempt->cod_sis,
            'estudiante' => $student === null ? null : [
                'id_estudiante' => (int) $student->id_estudiante,
                'cod_sis' => $student->cod_sis,
                'nombre_completo' => trim(implode(' ', array_filter([
                    $student->nombre, $student->apellido_paterno, $student->apellido_materno,
                ]))),
            ],
            'ambiente' => [
                'id_ambiente' => (int) $attempt->id_ambiente,
                'nro_aula' => $this['room']->nro_aula,
            ],
            'motivo' => $attempt->motivo,
            'observacion' => $attempt->observacion,
            'registrado_en' => $attempt->registrado_en->toIso8601String(),
            'reportado' => $attempt->studentReport !== null,
        ];
    }
}