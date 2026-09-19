<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\ExamType;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona la creación de nuevos exámenes y la validación de disponibilidad de ambientes.
 */
class ExamCreationService
{
    protected ExamGroupService $groupService;

    public function __construct(ExamGroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    public function createExam(array $data): Exam
    {
        $startTime = Carbon::createFromFormat('H:i', $data['hora_inicio']);
        $endTime = (clone $startTime)->addMinutes($data['duracion'])->format('H:i');

        $roomOccupied = DB::table('examen')
            ->join('examen_ambiente', 'examen.id_examen', '=', 'examen_ambiente.id_examen')
            ->whereIn('examen_ambiente.id_ambiente', $data['ambientes'])
            ->where('examen.fecha', $data['fecha'])
            ->where(function ($query) use ($data, $endTime) {
                $query->whereBetween('examen.hora_inicio', [$data['hora_inicio'], $endTime])
                      ->orWhereBetween('examen.hora_fin', [$data['hora_inicio'], $endTime])
                      ->orWhere(function ($q) use ($data, $endTime) {
                          $q->where('examen.hora_inicio', '<=', $data['hora_inicio'])
                            ->where('examen.hora_fin', '>=', $endTime);
                      });
            })
            ->exists();

        if ($roomOccupied) {
            throw new Exception('Uno o más ambientes seleccionados ya están reservados en ese horario');
        }

        $category = $data['categoria'] ?? 'REGULAR';
        $examType = ExamType::firstOrCreate(
            ['categoria' => $category],
            ['nombre' => $data['nombre_examen']]
        );

        return DB::transaction(function () use ($data, $endTime, $examType) {
            $exam = Exam::create([
                'nombre_examen'  => $data['nombre_examen'],
                'fecha'          => $data['fecha'],
                'hora_inicio'    => $data['hora_inicio'],
                'hora_fin'       => $endTime,
                'duracion'       => $data['duracion'],
                'normas'         => $data['normas'] ?? null,
                'id_tipo_examen' => $examType->id_tipo_examen,
            ]);

            $exam->classrooms()->attach($data['ambientes']);

            if (!empty($data['grupos'])) {
                $exam->groups()->attach($data['grupos']);
                $this->groupService->syncExamStudents($exam->id_examen, $data['grupos']);
            }

            return $exam;
        });
    }
}
