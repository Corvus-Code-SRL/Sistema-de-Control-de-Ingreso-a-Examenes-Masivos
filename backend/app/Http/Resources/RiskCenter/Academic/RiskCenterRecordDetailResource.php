<?php

namespace App\Http\Resources\RiskCenter\Academic;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de un registro de la Central de Riesgo.
 *
 * La evidencia no se expone como URL pública: se pide a GET /central-riesgo/{id}/evidencia. Aquí solo
 * viajan su nombre, su tamaño y si el archivo está disponible.
 */
class RiskCenterRecordDetailResource extends JsonResource
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
                'carrera' => $exam->career->nombre ?? null,
                'fecha' => $exam->fecha->toDateString(),
            ],
            'ambiente' => $attempt === null ? null : [
                'nro_aula' => $attempt->classroom->nro_aula ?? null,
                'hora_intento' => $attempt->registrado_en->toIso8601String(),
            ],
            'descripcion' => $report->descripcion,
            'reportante' => [
                'nombre_completo' => $report->userReporter->nombre_completo,
                'fecha' => $report->fecha_reporte->toIso8601String(),
            ],
            'aprobador' => $reviewer === null ? null : [
                'nombre_completo' => $reviewer->nombre_completo,
                'fecha' => $report->fecha_revision->toIso8601String(),
            ],
            'observacion' => $report->observacion_revision,
            'fecha_registro' => $this->fecha_registro->toIso8601String(),
            'evidencia' => $this->evidence,
        ];
    }
}
