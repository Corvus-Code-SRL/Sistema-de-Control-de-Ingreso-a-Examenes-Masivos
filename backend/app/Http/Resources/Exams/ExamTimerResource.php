<?php

namespace App\Http\Resources\Exams;

use App\Services\Exams\ExamTimingService;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamTimerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_examen' => $this->id_examen,
            'nombre_examen' => $this->nombre_examen,
            'fecha' => $this->fecha->toDateString(),
            'hora_inicio' => substr($this->hora_inicio, 0, 5),
            'hora_fin' => $this->hora_fin === null ? null : substr($this->hora_fin, 0, 5),
            'duracion' => $this->duracion,
            'estado' => $this->estado,
            'materia' => $this->whenLoaded('subject', fn () => ['nombre' => $this->subject->nombre]),
            'carrera' => $this->whenLoaded('career', fn () => ['nombre' => $this->career->nombre]),
            'fin_permitido_en' => (int) $this->duracion > 0
                ? app(ExamTimingService::class)->resolutionLimit($this->resource)->toIso8601String()
                : null,
            'cronometro_iniciado_en' => $this->cronometro_iniciado_en?->utc()->toIso8601String(),
            'cronometro_vence_en' => $this->cronometro_vence_en?->utc()->toIso8601String(),
        ];
    }
}
