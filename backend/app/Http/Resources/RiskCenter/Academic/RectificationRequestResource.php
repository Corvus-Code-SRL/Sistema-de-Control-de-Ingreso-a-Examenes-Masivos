<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarjeta de una solicitud de rectificación para la bandeja del docente (HU-062): el registro, quién lo pide y
 * por qué, y lo que respondió el docente si ya lo hizo.
 *
 * Requiere los datos que carga RectificationRequestQueryService (registro, reporte, estudiante, examen,
 * solicitante y los atributos `rechazos_previos`, `evidencia_reporte_info` y `evidencia_rechazo_info`).
 * `estado` es el efectivo: una pendiente pasada de fecha se muestra VENCIDA.
 */
class RectificationRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        $record = $this->record;
        $report = $record->studentReport;
        $exam = $report->exam;
        $attempt = $report->entryAttempt;

        return [
            'id_solicitud_rect' => (int) $this->id_solicitud_rect,
            'id_cr_estudiante' => (int) $this->id_cr_estudiante,
            'estado' => $this->effectiveState(),
            'fecha_solicitud' => $this->fecha_solicitud->toIso8601String(),
            'vence_en' => $this->vence_en->toIso8601String(),
            'fecha_respuesta' => $this->fecha_respuesta?->toIso8601String(),
            'motivo_solicitud' => $this->motivo_solicitud,
            'administrador' => ['nombre_completo' => $this->requester->nombre_completo],
            'estudiante' => [
                'id_estudiante' => (int) $record->student->id_estudiante,
                'nombre_completo' => $record->student->nombre_completo,
                'cod_sis' => $record->student->cod_sis,
            ],
            'examen' => [
                'id_examen' => (int) $exam->id_examen,
                'nombre' => $exam->nombre_examen,
                'materia' => $exam->subject->nombre ?? null,
                'fecha' => $exam->fecha->toDateString(),
            ],
            'hecho' => [
                'fecha_hora' => ($attempt?->registrado_en ?? $report->fecha_reporte)->toIso8601String(),
            ],
            'tipo_falta' => [
                'id_falta' => (int) $report->incidentType->id_falta,
                'nombre' => $report->incidentType->nombre,
            ],
            'descripcion' => $report->descripcion,
            'fecha_registro' => $record->fecha_registro->toIso8601String(),
            'evidencia' => $this->getAttribute('evidencia_reporte_info'),
            'rechazos_previos' => (int) $this->getAttribute('rechazos_previos'),
            'registro_rectificado' => $record->isRectified(),
            'respuesta' => $this->estado === 'RECHAZADA' ? [
                'motivo' => $this->motivo_rechazo,
                'evidencia' => $this->getAttribute('evidencia_rechazo_info'),
            ] : null,
        ];
    }
}
