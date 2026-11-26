<?php

namespace App\Http\Resources\Exams;

use App\Services\Exams\Distribution\RoomDistributionQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @phpstan-import-type ExamOption from RoomDistributionQueryService */
class DistributionExamOptionResource extends JsonResource
{
    /**
     * @param Request $request
     * @return ExamOption
     */
    public function toArray($request): array
    {
        return [
            'id_examen' => $this->resource['id_examen'],
            'nombre' => $this->resource['nombre'],
            'materia' => $this->resource['materia'],
            'fecha' => $this->resource['fecha'],
            'hora_inicio' => $this->resource['hora_inicio'],
            'estado' => $this->resource['estado'],
            'cantidad_ambientes' => $this->resource['cantidad_ambientes'],
            'es_creador' => $this->resource['es_creador'],
            'editable' => $this->resource['editable'],
            'distribucion_suspendida' => $this->resource['distribucion_suspendida'],
            'puede_dejar_de_usar' => $this->resource['puede_dejar_de_usar'],
        ];
    }
}
