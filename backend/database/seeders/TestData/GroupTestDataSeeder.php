<?php

namespace Database\Seeders\TestData;

use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Estudiantes, grupos y nóminas de prueba.
 *
 * Los grupos cubren los casos del módulo Academic: grupos propios y ajenos en el mismo
 * par, el mismo número de grupo en la misma materia de otra carrera, un grupo sin
 * nómina, uno de un par inactivo y uno de un periodo anterior.
 */
class GroupTestDataSeeder extends Seeder
{
    private const ENROLLMENT_DATE = '2026-08-03';

    /** [id, cod_sis, ci, nombre, apellido paterno, apellido materno] */
    private const STUDENTS = [
        [9501, '202150001', '7100001', 'Adriana', 'Arnez', 'Céspedes'],
        [9502, '202150002', '7100002', 'Bruno', 'Balderrama', 'Ortiz'],
        [9503, '202150003', '7100003', 'Camila', 'Camacho', 'Lazarte'],
        [9504, '202150004', '7100004', 'Diego', 'Delgadillo', 'Meneses'],
        [9505, '202150005', '7100005', 'Elena', 'Escóbar', 'Nogales'],
        [9506, '202150006', '7100006', 'Fabián', 'Fuentes', 'Orellana'],
        [9507, '202150007', '7100007', 'Gabriela', 'Guzmán', 'Pinto'],
        [9508, '202150008', '7100008', 'Hernán', 'Heredia', 'Quispe'],
        [9509, '202150009', '7100009', 'Inés', 'Irahola', 'Rojas'],
        [9510, '202150010', '7100010', 'Jorge', 'Jaldín', 'Saavedra'],
        [9511, '202150011', '7100011', 'Karen', 'Kempff', 'Tapia'],
        [9512, '202150012', '7100012', 'Luis', 'Lora', 'Ugarte'],
        [9513, '202150013', '7100013', 'Mónica', 'Montero', 'Vargas'],
        [9514, '202150014', '7100014', 'Nicolás', 'Navia', 'Zambrana'],
        [9515, '202150015', '7100015', 'Olga', 'Orozco', 'Alcócer'],
        [9516, '202150016', '7100016', 'Pablo', 'Peredo', 'Bustos'],
        [9517, '202150017', '7100017', 'Raquel', 'Rivero', 'Claure'],
        [9518, '202150018', '7100018', 'Sergio', 'Siles', 'Durán'],
        [9519, '202150019', '7100019', 'Tatiana', 'Torrico', 'Encinas'],
        [9520, '202150020', '7100020', 'Ulises', 'Urquidi', 'Flores'],
        [9521, '202150021', '7100021', 'Verónica', 'Vallejos', 'Gamboa'],
        [9522, '202150022', '7100022', 'Wálter', 'Weise', 'Hinojosa'],
        [9523, '202150023', '7100023', 'Ximena', 'Ximénez', 'Ibáñez'],
        [9524, '202150024', '7100024', 'Yolanda', 'Yañez', 'Jiménez'],
    ];

    /** id de grupo => estudiantes con inscripción ACTIVA */
    private const ROSTERS = [
        Ids::GROUP_CALCULUS_SYS_1 => [9501, 9502, 9503, 9504, 9505, 9506],
        Ids::GROUP_CALCULUS_SYS_2 => [9508, 9509, 9510, 9511, 9512],
        Ids::GROUP_CALCULUS_ECO_1 => [9513, 9514, 9515, 9516],
        Ids::GROUP_ALGEBRA_INF_1 => [9517, 9518, 9519, 9520, 9521],
        Ids::GROUP_PROGRAMMING_SYS_1 => [9501, 9502, 9522, 9523],
        Ids::GROUP_PROGRAMMING_SYS_2 => [9503, 9504, 9524],
        Ids::GROUP_STATISTICS_ECO_1 => [9513, 9514, 9515],
        Ids::GROUP_DATABASES_INF_1 => [9517, 9518],
        Ids::GROUP_CALCULUS_SYS_1_PREVIOUS => [9505, 9506],
        Ids::GROUP_ACCOUNTING_ADM_1 => [9519, 9520, 9521],
        // GROUP_DATABASES_SYS_1 no tiene filas: es el grupo sin nómina.
    ];

    public function run()
    {
        DB::table('estudiante')->upsert(array_map(fn (array $student) => [
            'id_estudiante' => $student[0],
            'cod_sis' => $student[1],
            'ci' => $student[2],
            'nombre' => $student[3],
            'apellido_paterno' => $student[4],
            'apellido_materno' => $student[5],
            'correo_institucional' => "{$student[1]}@est.sciem.test",
            'telefono' => null,
            'estado' => 'ACTIVO',
        ], self::STUDENTS), ['id_estudiante']);

        [$sys, $inf, $eco, $adm] = [
            Ids::CAREER_SYSTEMS,
            Ids::CAREER_INFORMATICS,
            Ids::CAREER_ECONOMICS,
            Ids::CAREER_BUSINESS,
        ];
        [$fixed, $teacher2, $teacher3] = [Ids::TEACHER_FIXED, Ids::TEACHER_2, Ids::TEACHER_3];

        DB::table('grupo')->upsert([
            // Mismo par (Sistemas, Cálculo I): grupo 1 del docente fijo, grupo 2 de otro docente.
            $this->group(Ids::GROUP_CALCULUS_SYS_1, $sys, Ids::SUBJECT_CALCULUS, '1', $fixed),
            $this->group(Ids::GROUP_CALCULUS_SYS_2, $sys, Ids::SUBJECT_CALCULUS, '2', $teacher2),
            // Grupo 1 de Cálculo I también en Economía (otra facultad): convive con el de Sistemas.
            $this->group(Ids::GROUP_CALCULUS_ECO_1, $eco, Ids::SUBJECT_CALCULUS, '1', $fixed),
            $this->group(Ids::GROUP_DATABASES_SYS_1, $sys, Ids::SUBJECT_DATABASES, '1', $fixed),
            $this->group(Ids::GROUP_ALGEBRA_INF_1, $inf, Ids::SUBJECT_ALGEBRA, '1', $teacher3),
            $this->group(Ids::GROUP_PROGRAMMING_SYS_1, $sys, Ids::SUBJECT_PROGRAMMING, '1', $teacher2),
            $this->group(Ids::GROUP_PROGRAMMING_SYS_2, $sys, Ids::SUBJECT_PROGRAMMING, '2', $fixed),
            $this->group(Ids::GROUP_STATISTICS_ECO_1, $eco, Ids::SUBJECT_STATISTICS, '1', $teacher3),
            // Grupo del docente fijo en un par INACTIVO: existe, pero su detalle responde 422.
            $this->group(Ids::GROUP_DATABASES_INF_1, $inf, Ids::SUBJECT_DATABASES, '1', $fixed),
            // Mismo par y número que GROUP_CALCULUS_SYS_1, pero de un periodo anterior.
            $this->group(
                Ids::GROUP_CALCULUS_SYS_1_PREVIOUS,
                $sys,
                Ids::SUBJECT_CALCULUS,
                '1',
                $fixed,
                Ids::PERIOD_2026_1
            ),
            // Par donde el docente fijo no dicta ningún grupo.
            $this->group(Ids::GROUP_ACCOUNTING_ADM_1, $adm, Ids::SUBJECT_ACCOUNTING, '1', $teacher2),
        ], ['id_grupo']);

        $enrollments = [];

        foreach (self::ROSTERS as $groupId => $studentIds) {
            foreach ($studentIds as $studentId) {
                $enrollments[] = $this->enrollment($groupId, $studentId, 'ACTIVO');
            }
        }

        // Retiro: la fila queda INACTIVA y no cuenta como inscrito.
        $enrollments[] = $this->enrollment(Ids::GROUP_CALCULUS_SYS_1, 9507, 'INACTIVO');

        DB::table('grupo_estudiante')->upsert($enrollments, ['id_grupo', 'id_estudiante']);
    }

    private function group(
        int $id,
        int $careerId,
        int $subjectId,
        string $number,
        string $teacherId,
        int $periodId = Ids::PERIOD_ACTIVE
    ): array {
        return [
            'id_grupo' => $id,
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'num_grupo' => $number,
            'gestion' => '2026',
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $teacherId,
            'id_periodo' => $periodId,
        ];
    }

    private function enrollment(int $groupId, int $studentId, string $status): array
    {
        return [
            'id_grupo' => $groupId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => self::ENROLLMENT_DATE,
            'estado' => $status,
        ];
    }
}
