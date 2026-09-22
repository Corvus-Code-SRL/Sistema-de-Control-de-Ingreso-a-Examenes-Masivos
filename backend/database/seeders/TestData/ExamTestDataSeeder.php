<?php

namespace Database\Seeders\TestData;

use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Exámenes ficticios con fechas fijas, ambientes y grupos del mismo par materia-carrera. */
class ExamTestDataSeeder extends Seeder
{
    public function run()
    {
        DB::table('examen')->upsert([
            $this->exam(9801, 'Parcial Cálculo SIS', '2026-10-15', '08:00:00', '09:30:00', 90,
                Ids::CAREER_SYSTEMS, Ids::SUBJECT_CALCULUS, Ids::TEACHER_FIXED, 'PROGRAMADO'),
            $this->exam(9802, 'Final Cálculo ECO', '2026-11-20', '10:00:00', '12:00:00', 120,
                Ids::CAREER_ECONOMICS, Ids::SUBJECT_CALCULUS, Ids::TEACHER_FIXED, 'PROGRAMADO', 9702),
            $this->exam(9803, 'Parcial de Rosario', '2026-10-15', '08:00:00', '09:30:00', 90,
                Ids::CAREER_SYSTEMS, Ids::SUBJECT_CALCULUS, Ids::TEACHER_2, 'PROGRAMADO'),
            $this->exam(9804, 'BD cancelado', '2026-10-16', '14:00:00', '15:00:00', 60,
                Ids::CAREER_SYSTEMS, Ids::SUBJECT_DATABASES, Ids::TEACHER_FIXED, 'CANCELADO'),
            $this->exam(9805, 'Programación ingreso', '2026-09-21', '09:00:00', '10:00:00', 60,
                Ids::CAREER_SYSTEMS, Ids::SUBJECT_PROGRAMMING, Ids::TEACHER_FIXED, 'EN_INGRESO'),
            $this->exam(9806, 'Álgebra en curso', '2026-09-21', '10:00:00', '11:30:00', 90,
                Ids::CAREER_INFORMATICS, Ids::SUBJECT_ALGEBRA, Ids::TEACHER_3, 'EN_CURSO'),
            $this->exam(9807, 'Cálculo finalizado', '2026-09-10', '08:00:00', '09:00:00', 60,
                Ids::CAREER_SYSTEMS, Ids::SUBJECT_CALCULUS, Ids::TEACHER_FIXED, 'FINALIZADO'),
            $this->exam(9808, 'Programación nocturno', '2026-10-17', '23:00:00', '01:00:00', 120,
                Ids::CAREER_SYSTEMS, Ids::SUBJECT_PROGRAMMING, Ids::TEACHER_FIXED, 'PROGRAMADO'),
        ], ['id_examen']);

        $classrooms = [9801 => 9601, 9802 => 9605, 9803 => 9602, 9804 => 9603,
            9805 => 9604, 9806 => 9603, 9807 => 9601, 9808 => 9604];
        $groups = [9801 => Ids::GROUP_CALCULUS_SYS_1, 9802 => Ids::GROUP_CALCULUS_ECO_1,
            9803 => Ids::GROUP_CALCULUS_SYS_2, 9804 => Ids::GROUP_DATABASES_SYS_1,
            9805 => Ids::GROUP_PROGRAMMING_SYS_2, 9806 => Ids::GROUP_ALGEBRA_INF_1,
            9807 => Ids::GROUP_CALCULUS_SYS_1, 9808 => Ids::GROUP_PROGRAMMING_SYS_2];

        foreach ($classrooms as $examId => $classroomId) {
            DB::table('examen_ambiente')->upsert([
                ['id_examen' => $examId, 'id_ambiente' => $classroomId],
            ], ['id_examen', 'id_ambiente']);
        }

        foreach ($groups as $examId => $groupId) {
            DB::table('grupo_examen')->upsert([
                ['id_examen' => $examId, 'id_grupo' => $groupId],
            ], ['id_grupo', 'id_examen']);
        }
    }

    private function exam(
        int $id,
        string $name,
        string $date,
        string $start,
        string $end,
        int $duration,
        int $careerId,
        int $subjectId,
        string $teacherId,
        string $status,
        int $typeId = 9701
    ): array {
        return [
            'id_examen' => $id,
            'nombre_examen' => $name,
            'fecha' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'duracion' => $duration,
            'normas' => 'Datos ficticios para desarrollo. Presentar identificación.',
            'id_tipo_examen' => $typeId,
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'id_usuario_docente' => $teacherId,
            'estado' => $status,
        ];
    }
}
