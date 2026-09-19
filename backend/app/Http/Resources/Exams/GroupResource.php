<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * Transforma la información de un grupo académico a formato JSON.
 */
class GroupResource extends JsonResource
{
    public function toArray($request): array
    {
        $groupId = $this->id_grupo;

        if (isset($this->cantidad_estudiantes)) {
            $studentCount = (int) $this->cantidad_estudiantes;
        } elseif ($this->relationLoaded('students')) {
            $studentCount = $this->students->count();
        } else {
            $studentCount = DB::table('grupo_estudiante')
                ->where('id_grupo', $groupId)
                ->where('estado', 'ACTIVO')
                ->count();
        }

        return [
            'id_grupo'             => $this->id_grupo,
            'id_materia'           => $this->id_materia ?? null,
            'num_grupo'            => $this->num_grupo,
            'gestion'              => $this->gestion,
            'estado'               => $this->estado,
            'cantidad_estudiantes' => $studentCount,
            'tiene_nomina'         => $studentCount > 0,
        ];
    }
}
