<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un registro activo en la lista de la vista de rectificación (HU-062): lo justo para reconocerlo y elegirlo.
 *
 * Requiere cargar studentReport (con incidentType, exam.subject y userReviewer).
 */
class RectifiableRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        $report = $this->studentReport;
        $exam = $report->exam;
        $reviewer = $report->userReviewer;

        return [
            'id_cr_estudiante' => (int) $this->id_cr_estudiante,
            'fecha_registro' => $this->fecha_registro->toIso8601String(),
            'examen' => [
                'id_examen' => (int) $exam->id_examen,
                'nombre' => $exam->nombre_examen,
                'materia' => $exam->subject->nombre ?? null,
                'fecha' => $exam->fecha->toDateString(),
            ],
            'tipo_falta' => [
                'id_falta' => (int) $report->incidentType->id_falta,
                'nombre' => $report->incidentType->nombre,
            ],
            'aprobador' => $reviewer === null ? null : [
                'nombre_completo' => $reviewer->nombre_completo,
                'fecha' => $report->fecha_revision->toIso8601String(),
            ],
        ];
    }
}
