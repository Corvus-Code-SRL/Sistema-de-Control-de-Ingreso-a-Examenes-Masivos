<?php

namespace App\Services\Exams;

use Illuminate\Support\Facades\Auth;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class ExamGetAllService
{
    public function getAllExams()
    {
        $teacherId = Auth::id() ?? DB::table('usuario')->where('estado', 'ACTIVO')->value('id_usuario');

        $query = Exam::with(['groups.subject', 'examType', 'classrooms'])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc');

        if ($teacherId) {
            $teacherExams = (clone $query)->whereHas('groups', function ($q) use ($teacherId) {
                $q->where('id_usuario_docente', $teacherId);
            })->get();

            if ($teacherExams->isNotEmpty()) {
                return $teacherExams;
            }
        }

        return $query->get();
    }
}
