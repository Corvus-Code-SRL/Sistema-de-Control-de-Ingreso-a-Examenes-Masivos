<?php

namespace App\Http\Resources\RiskCenter\Academic;

use App\Models\RiskCenterVersion;

/**
 * Detalle de un registro en la vista de rectificación: lo que no se puede cambiar (estudiante, examen,
 * fecha, evidencia, quien reportó y quien aprobó), lo vigente y el historial completo, la versión más nueva
 * primero. Un registro nunca rectificado muestra su valor original como versión 1 vigente.
 *
 * La evidencia solo viaja con su nombre y si está disponible: esta vista no la enlaza.
 */
class RectifiableRecordDetailResource extends RectifiableRecordResource
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

        return array_merge(parent::toArray($request), [
            'estudiante' => [
                'id_estudiante' => (int) $this->student->id_estudiante,
                'nombre_completo' => $this->student->nombre_completo,
                'cod_sis' => $this->student->cod_sis,
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
            'descripcion' => $this->effectiveDescription(),
            'reportante' => [
                'nombre_completo' => $report->userReporter->nombre_completo,
                'fecha' => $report->fecha_reporte->toIso8601String(),
            ],
            'evidencia' => $this->evidence,
            'historial' => $this->history(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function history(): array
    {
        if ($this->versions->isEmpty()) {
            return [$this->originalVersion()];
        }

        return $this->versions->map(fn (RiskCenterVersion $version) => [
            'numero_version' => (int) $version->numero_version,
            'estado' => $version->es_vigente ? 'Vigente' : 'Anterior',
            'tipo_falta' => [
                'id_falta' => (int) $version->incidentType->id_falta,
                'nombre' => $version->incidentType->nombre,
            ],
            'descripcion' => $version->descripcion,
            'motivo' => $version->motivo,
            'autor' => ['nombre_completo' => $version->author->nombre_completo],
            'fecha' => $version->fecha_registro->toIso8601String(),
        ])->all();
    }

    /** La versión 1 de un registro que nunca se rectificó: los valores del reporte aprobado. */
    private function originalVersion(): array
    {
        $report = $this->studentReport;
        $author = $report->userReviewer ?? $report->userReporter;

        return [
            'numero_version' => 1,
            'estado' => 'Vigente',
            'tipo_falta' => [
                'id_falta' => (int) $report->incidentType->id_falta,
                'nombre' => $report->incidentType->nombre,
            ],
            'descripcion' => $report->descripcion,
            'motivo' => null,
            'autor' => ['nombre_completo' => $author->nombre_completo],
            'fecha' => $this->fecha_registro->toIso8601String(),
        ];
    }
}
