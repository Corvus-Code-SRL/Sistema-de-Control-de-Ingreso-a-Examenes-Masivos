<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/** Una fila del listado de la Central de Riesgo: un reporte aprobado. */
class RiskCenterRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        $report = $this->studentReport;
        $exam = $report->exam;

        return [
            'id_cr_estudiante' => (int) $this->id_cr_estudiante,
            'estudiante' => [
                'id_estudiante' => (int) $this->student->id_estudiante,
                'nombre_completo' => $this->student->nombre_completo,
                'cod_sis' => $this->student->cod_sis,
            ],
            'tipo_falta' => [
                'id_falta' => (int) $report->incidentType->id_falta,
                'nombre' => $report->incidentType->nombre,
            ],
            'examen' => [
                'id_examen' => (int) $exam->id_examen,
                'nombre' => $exam->nombre_examen,
                'materia' => $exam->subject->nombre ?? null,
            ],
            'fecha_registro' => $this->fecha_registro->toIso8601String(),
        ];
    }
}
