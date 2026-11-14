<?php

namespace App\Http\Resources\Exams;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttendanceDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $exam = $this->resource['exam'];
        $classroom = $this->resource['classroom'];
        $students = $this->resource['students'];
        $canViewCi = $this->resource['can_view_ci'];
        $roomNames = $this->resource['rooms']->pluck('nro_aula', 'id_ambiente');

        return [
            'id_examen' => (int) $exam->id_examen,
            'estado' => $exam->estado,
            'id_ambiente_asignado' => $classroom === null ? null : (int) $classroom->id_ambiente,
            'ambientes' => $this->resource['rooms']->map(static fn (array $room): array => [
                'id_ambiente' => $room['id_ambiente'],
                'nro_aula' => $room['nro_aula'],
            ])->all(),
            'estudiantes' => $students->map(static fn (object $student): array => [
                'id_estudiante' => (int) $student->id_estudiante,
                'cod_sis' => $student->cod_sis,
                'nombre_completo' => trim(implode(' ', array_filter([
                    $student->nombre, $student->apellido_paterno, $student->apellido_materno,
                ]))),
                'carrera' => $student->id_carrera === null ? null : [
                    'id_carrera' => (int) $student->id_carrera,
                    'nombre' => $student->carrera,
                ],
                'id_ambiente' => $student->id_ambiente,
                'ambiente' => $roomNames->get($student->id_ambiente),
                'estado' => $student->estado,
                'hora_ingreso' => $student->hora_ingreso === null ? null : substr($student->hora_ingreso, 0, 5),
                'expulsado_en' => $student->expulsado_en === null
                    ? null : Carbon::parse($student->expulsado_en)->utc()->toIso8601String(),
            ] + ($canViewCi ? ['ci' => $student->ci] : []))->all(),
            'totales' => $this->resource['totals'],
        ];
    }
}
