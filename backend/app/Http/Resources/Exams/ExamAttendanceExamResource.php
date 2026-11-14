<?php

namespace App\Http\Resources\Exams;

use App\Models\Exam;
use App\Models\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttendanceExamResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Exam $exam */
        $exam = $this->resource;
        $subject = $exam->getRelation('subject');
        $classroom = $exam->getRelation('attendanceClassroom');

        return [
            'id_examen' => (int) $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'materia' => $subject === null ? null : [
                'id_materia' => (int) $subject->id_materia,
                'nombre' => $subject->nombre,
                'codigo' => $subject->codigo,
            ],
            'grupos' => $exam->getRelation('groups')->map(static fn (Group $group): array => [
                'id_grupo' => (int) $group->id_grupo,
                'num_grupo' => $group->num_grupo,
            ])->all(),
            'fecha' => $exam->fecha->toDateString(),
            'hora_inicio' => substr($exam->hora_inicio, 0, 5),
            'hora_fin' => $exam->hora_fin === null ? null : substr($exam->hora_fin, 0, 5),
            'estado' => $exam->estado,
            'ambiente' => $classroom === null ? null : [
                'id_ambiente' => (int) $classroom->id_ambiente,
                'nro_aula' => $classroom->nro_aula,
            ],
        ];
    }
}
