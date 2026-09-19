<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona la consulta y vinculación de grupos académicos a los exámenes.
 */
class ExamGroupService
{
    /**
     * Obtiene los grupos del docente para una materia con el conteo de estudiantes mediante eager loading.
     */
    public function getGroupsBySubject(int $subjectId, ?string $teacherId = null)
    {
        $query = Group::with(['students' => function ($q) {
            $q->where('grupo_estudiante.estado', 'ACTIVO');
        }])
        ->where('id_materia', $subjectId)
        ->where('estado', 'ACTIVO');

        if ($teacherId) {
            $query->where('id_usuario_docente', $teacherId);
        }

        return $query->get()->map(function ($group) {
            $group->cantidad_estudiantes = $group->students->count();
            $group->tiene_nomina = $group->cantidad_estudiantes > 0;
            return $group;
        });
    }

    /**
     * Asigna los grupos a un examen y sincroniza la nómina de estudiantes.
     */
    public function assignGroups(Exam $exam, array $groupIds): Exam
    {
        return DB::transaction(function () use ($exam, $groupIds) {
            $exam->groups()->sync($groupIds);
            $this->syncExamStudents($exam->id_examen, $groupIds);

            return $exam->fresh(['groups', 'classrooms']);
        });
    }

    /**
     * Sincroniza la nómina de estudiantes en lote usando el método nativo upsert() de Laravel.
     */
    public function syncExamStudents(int $examId, array $groupIds): void
    {
        if (empty($groupIds)) {
            DB::table('examen_estudiante')->where('id_examen', $examId)->delete();
            return;
        }

        DB::table('examen_estudiante')
            ->where('id_examen', $examId)
            ->whereNotIn('id_grupo', $groupIds)
            ->delete();

        $enrolledStudents = DB::table('grupo_estudiante')
            ->whereIn('id_grupo', $groupIds)
            ->where('estado', 'ACTIVO')
            ->get(['id_grupo', 'id_estudiante']);

        if ($enrolledStudents->isEmpty()) {
            return;
        }

        $records = $enrolledStudents->map(fn ($item) => [
            'id_examen'           => $examId,
            'id_estudiante'       => $item->id_estudiante,
            'id_grupo'            => $item->id_grupo,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso'      => 'NO_INGRESO',
        ])->toArray();

        DB::table('examen_estudiante')->upsert(
            $records,
            ['id_examen', 'id_estudiante'],
            ['id_grupo', 'estado_habilitacion']
        );
    }
}
