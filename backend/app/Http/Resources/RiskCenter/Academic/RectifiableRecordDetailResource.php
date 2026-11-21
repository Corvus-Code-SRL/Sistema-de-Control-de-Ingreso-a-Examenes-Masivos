<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha completa de un registro activo para la tarjeta de solo lectura de la vista de rectificación (HU-062):
 * estudiante, examen, hecho, falta, descripción, evidencia, quien reportó y quien aprobó. Nada de esto se edita.
 *
 * Incluye `rectificacion`: el resumen de solicitudes que arma RectificationRequestQueryService.
 *
 * La evidencia viaja solo con su nombre y si está disponible; el archivo se pide a GET /central-riesgo/{id}/evidencia.
 */
class RectifiableRecordDetailResource extends JsonResource
{
    /** @var array{nombre_archivo: string, tamano_bytes: int|null, disponible: bool}|null */
    private ?array $evidence;

    public function __construct($resource, ?array $evidence)
    {
        parent::__construct($resource);

        $this->evidence = $evidence;
    }

    public function toArray($request): array
    {
        $report = $this->studentReport;
        $exam = $report->exam;
        $attempt = $report->entryAttempt;
        $reviewer = $report->userReviewer;
        $room = $report->origen === 'EN_CURSO' ? $report->incidentRoom : $attempt?->classroom;

        return [
            'id_cr_estudiante' => (int) $this->id_cr_estudiante,
            'estudiante' => [
                'id_estudiante' => (int) $this->student->id_estudiante,
                'nombre_completo' => $this->student->nombre_completo,
                'cod_sis' => $this->student->cod_sis,
                'ci' => $this->student->ci,
            ],
            'examen' => [
                'id_examen' => (int) $exam->id_examen,
                'nombre' => $exam->nombre_examen,
                'materia' => $exam->subject->nombre ?? null,
                'carrera' => $exam->career->nombre ?? null,
                'fecha' => $exam->fecha->toDateString(),
            ],
            'hecho' => [
                'fecha_hora' => ($attempt?->registrado_en ?? $report->fecha_reporte)->toIso8601String(),
            ],
            'ambiente' => $room === null ? null : ['nro_aula' => $room->nro_aula],
            'tipo_falta' => [
                'id_falta' => (int) $report->incidentType->id_falta,
                'nombre' => $report->incidentType->nombre,
            ],
            'descripcion' => $report->descripcion,
            'evidencia' => $this->evidence,
            'reportante' => [
                'nombre_completo' => $report->userReporter->nombre_completo,
                'fecha' => $report->fecha_reporte->toIso8601String(),
            ],
            'aprobador' => $reviewer === null ? null : [
                'nombre_completo' => $reviewer->nombre_completo,
                'fecha' => $report->fecha_revision->toIso8601String(),
            ],
            'fecha_registro' => $this->fecha_registro->toIso8601String(),
            'rectificacion' => $this->getAttribute('resumen_rectificacion'),
        ];
    }
}
