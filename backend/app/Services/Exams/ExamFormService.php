<?php

namespace App\Services\Exams;

use App\Models\Group;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Provee la información de referencia necesaria para el formulario de configuración de exámenes.
 */
class ExamFormService
{
    public function getFormData(?string $teacherId)
    {
        if (!$teacherId) {
            throw new Exception('No se pudo obtener el id del usuario docente actual');
        }

        $groupsQuery = Group::with(['students' => function ($q) {
            $q->where('grupo_estudiante.estado', 'ACTIVO');
        }])
        ->where('estado', 'ACTIVO');

        if ($teacherId) {
            $groupsQuery->where('id_usuario_docente', $teacherId);
        }

        $groups = $groupsQuery->get()->map(function ($group) {
            $group->cantidad_estudiantes = $group->students->count();
            $group->tiene_nomina = $group->cantidad_estudiantes > 0;
            return $group;
        });

        $subjects = DB::table('materia')->where('estado', 'ACTIVO')->get();
        $classrooms = DB::table('ambiente')->where('estado', 'ACTIVO')->get();

        return [
            'materias'  => $subjects,
            'ambientes' => $classrooms,
            'grupos'    => $groups,
        ];
    }
}
