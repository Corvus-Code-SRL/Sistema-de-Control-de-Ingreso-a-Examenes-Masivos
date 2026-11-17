<?php

namespace App\Http\Resources\Exams;

use App\Services\EntryControl\CloseOrdinaryEntryService;
use App\Services\Exams\ExamTimingService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Examen con su par materia-carrera, su estado y el docente que lo creó.
 *
 * `es_creador` distingue al creador, que administra el examen, de un colaborador (HU-027), que solo lo ve y
 * lo controla: la interfaz oculta con él las acciones que el servidor responde con 403.
 */
class ExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen'          => $this->id_examen,
            'nombre_examen'      => $this->nombre_examen,
            'fecha'              => $this->fecha ? $this->fecha->format('Y-m-d') : null,
            'hora_inicio'        => $this->formatTime($this->hora_inicio),
            'hora_fin'           => $this->formatTime($this->hora_fin),
            'duracion'           => $this->duracion,
            'minutos_apertura'   => $this->minutos_apertura,
            'normas'             => $this->normas,
            'estado'             => $this->estado,
            'inicio_programado'  => $this->fecha && $this->hora_inicio
                ? app(ExamTimingService::class)->startsAt($this->resource)->toIso8601String()
                : null,
            'ingreso_abierto_en' => $this->ingreso_abierto_en ? $this->ingreso_abierto_en->toIso8601String() : null,
            'ingreso_ordinario_cerrado_en' => $this->ingreso_ordinario_cerrado_en
                ? $this->ingreso_ordinario_cerrado_en->toIso8601String()
                : null,
            'control_ingreso_abierto' => CloseOrdinaryEntryService::isOpen($this->resource),
            'fin_real_en'        => $this->fin_real_en ? $this->fin_real_en->toIso8601String() : null,
            'fin_automatico'     => (bool) $this->fin_automatico,
            'id_tipo_examen'     => $this->id_tipo_examen,
            'id_carrera'         => $this->id_carrera,
            'id_materia'         => $this->id_materia,
            'id_usuario_docente' => $this->id_usuario_docente,
            'es_creador'         => (string) $this->id_usuario_docente === (string) optional($request->user())->id_usuario,

            'tipo_examen' => $this->whenLoaded('examType', fn () => [
                'id_tipo_examen' => $this->examType->id_tipo_examen,
                'nombre'         => $this->examType->nombre,
                'categoria'      => $this->examType->categoria,
            ]),

            'materia' => $this->whenLoaded('subject', fn () => [
                'id_materia' => $this->subject->id_materia,
                'nombre'     => $this->subject->nombre,
                'codigo'     => $this->subject->codigo,
            ]),

            'carrera' => $this->whenLoaded('career', fn () => [
                'id_carrera' => $this->career->id_carrera,
                'nombre'     => $this->career->nombre,
            ]),

            'ambientes' => ClassroomResource::collection($this->whenLoaded('classrooms')),
            'grupos' => GroupResource::collection($this->whenLoaded('groups')),
        ];
    }

    /** PostgreSQL devuelve time como HH:MM:SS; el cliente trabaja con HH:MM. */
    private function formatTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
