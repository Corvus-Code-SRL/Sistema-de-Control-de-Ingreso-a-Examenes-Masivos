<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un registro confirmado en la vista de rectificación (HU-062), con el tipo de falta vigente y su estado:
 * «Original» si nunca se rectificó o «Rectificado», con la cantidad de rectificaciones y la fecha de la última.
 *
 * Requiere cargar studentReport (con incidentType, exam.subject y userReviewer), currentVersion.incidentType
 * y versions_count.
 */
class RectifiableRecordResource extends JsonResource
{
    public const STATE_ORIGINAL = 'Original';

    public const STATE_RECTIFIED = 'Rectificado';

    public function toArray($request): array
    {
        $report = $this->studentReport;
        $exam = $report->exam;
        $reviewer = $report->userReviewer;
        $rectifications = max(0, (int) $this->versions_count - 1);
        $type = $this->effectiveIncidentType();

        return [
            'id_cr_estudiante' => (int) $this->id_cr_estudiante,
            'fecha_registro' => $this->fecha_registro->toIso8601String(),
            'examen' => [
                'id_examen' => (int) $exam->id_examen,
                'nombre' => $exam->nombre_examen,
                'materia' => $exam->subject->nombre ?? null,
                'fecha' => $exam->fecha->toDateString(),
            ],
            'tipo_falta' => ['id_falta' => (int) $type->id_falta, 'nombre' => $type->nombre],
            'aprobador' => $reviewer === null ? null : [
                'nombre_completo' => $reviewer->nombre_completo,
                'fecha' => $report->fecha_revision->toIso8601String(),
            ],
            'estado' => $rectifications > 0 ? self::STATE_RECTIFIED : self::STATE_ORIGINAL,
            'numero_rectificaciones' => $rectifications,
            'ultima_rectificacion' => $rectifications > 0
                ? $this->currentVersion->fecha_registro->toIso8601String()
                : null,
            'version_vigente' => $this->currentVersion === null ? 1 : (int) $this->currentVersion->numero_version,
        ];
    }
}
