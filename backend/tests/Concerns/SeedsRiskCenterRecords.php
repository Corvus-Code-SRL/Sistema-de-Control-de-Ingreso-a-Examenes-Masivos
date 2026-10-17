<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Filas mínimas para probar reportes de estudiantes, intentos de ingreso y la central de riesgo.
 *
 * Inserta con DB::table para no depender de los modelos: sirve igual para probar el esquema y las
 * migraciones. Todo valor único lleva uniqid(), así que se puede llamar varias veces en una prueba.
 */
trait SeedsRiskCenterRecords
{
    /**
     * Docente (que a la vez es controlador y reportante), estudiante, tipo de falta, examen y un
     * ambiente asignado al examen: lo que necesitan un intento y un reporte.
     *
     * @return array{id_usuario: string, id_examen: int, id_ambiente: int, id_estudiante: int, id_tipo_falta: int}
     */
    protected function createReportingContext(): array
    {
        $teacherId = DB::table('usuario')->insertGetId([
            'nombre' => 'Docente',
            'apellido_paterno' => 'Reportante',
            'correo' => 'reportante.' . uniqid() . '@sciem.test',
            'contrasenia' => 'secreto',
            'cod_sis' => (string) random_int(100000000, 999999999),
            'estado' => 'ACTIVO',
        ], 'id_usuario');

        $typeId = DB::table('tipo_examen')->where('nombre', 'Regular')->value('id_tipo_examen')
            ?? DB::table('tipo_examen')->insertGetId(
                ['nombre' => 'Regular', 'categoria' => 'REGULAR'],
                'id_tipo_examen'
            );

        $examId = DB::table('examen')->insertGetId([
            'nombre_examen' => 'Examen de prueba',
            'fecha' => '2026-10-01',
            'hora_inicio' => '08:00',
            'id_tipo_examen' => $typeId,
            'id_usuario_docente' => $teacherId,
        ], 'id_examen');

        $classroomId = DB::table('ambiente')->insertGetId(
            ['nro_aula' => 'A' . substr(uniqid(), -8), 'capacidad' => 40, 'estado' => 'ACTIVO'],
            'id_ambiente'
        );
        DB::table('examen_ambiente')->insert(['id_examen' => $examId, 'id_ambiente' => $classroomId]);

        $studentId = DB::table('estudiante')->insertGetId([
            'cod_sis' => (string) random_int(100000000, 999999999),
            'nombre' => 'Nombre',
            'apellido_paterno' => 'Apellido',
            'estado' => 'ACTIVO',
        ], 'id_estudiante');

        return [
            'id_usuario' => $teacherId,
            'id_examen' => $examId,
            'id_ambiente' => $classroomId,
            'id_estudiante' => $studentId,
            'id_tipo_falta' => DB::table('tipo_falta')->insertGetId(['nombre' => 'Falta ' . uniqid()], 'id_falta'),
        ];
    }

    protected function insertEntryAttempt(array $context, array $overrides = []): int
    {
        return DB::table('intento_ingreso')->insertGetId(array_merge([
            'id_examen' => $context['id_examen'],
            'id_estudiante' => $context['id_estudiante'],
            'id_ambiente' => $context['id_ambiente'],
            'id_usuario_controlador' => $context['id_usuario'],
            'motivo' => 'DOCUMENTO_NO_VALIDO',
            'registrado_en' => '2026-10-01 08:00:00+00',
        ], $overrides), 'id_intento');
    }

    protected function insertStudent(string $name, string $paternal, ?string $maternal, string $sisCode): int
    {
        return DB::table('estudiante')->insertGetId([
            'cod_sis' => $sisCode,
            'nombre' => $name,
            'apellido_paterno' => $paternal,
            'apellido_materno' => $maternal,
            'estado' => 'ACTIVO',
        ], 'id_estudiante');
    }

    /**
     * Carrera y materia con su par activo, para que un examen pueda apuntar a ambas.
     *
     * @return array{id_carrera: int, id_materia: int}
     */
    protected function insertCareerSubjectPair(string $careerName, string $subjectName): array
    {
        $code = strtoupper(substr(uniqid(), -6));

        $facultyId = DB::table('facultad')->insertGetId(
            ['nombre' => 'Facultad ' . $code, 'codigo' => 'F' . $code, 'estado' => 'ACTIVO'],
            'id_facultad'
        );
        $careerId = DB::table('carrera')->insertGetId(
            ['nombre' => $careerName, 'codigo' => 'C' . $code, 'estado' => 'ACTIVO', 'id_facultad' => $facultyId],
            'id_carrera'
        );
        $subjectId = DB::table('materia')->insertGetId(
            ['nombre' => $subjectName, 'codigo' => 'M' . $code, 'estado' => 'ACTIVO'],
            'id_materia'
        );
        DB::table('materia_carrera')->insert([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'nivel_semestre' => '3',
            'obligatoria' => true,
            'estado' => 'ACTIVO',
        ]);

        return ['id_carrera' => $careerId, 'id_materia' => $subjectId];
    }

    protected function insertFaultType(string $name): int
    {
        return DB::table('tipo_falta')->insertGetId(['nombre' => $name], 'id_falta');
    }

    /**
     * Reporte APROBADO con su fila en la central de riesgo. El reportante y el aprobador son la cuenta del
     * contexto, salvo lo que se indique en $reportOverrides; `fecha_registro` es la fecha de la revisión.
     *
     * @return array{id_reporte_est: int, id_cr_estudiante: int}
     */
    protected function insertApprovedRecord(
        array $context,
        array $reportOverrides = [],
        string $registeredAt = '2026-10-02 12:00:00+00'
    ): array {
        $reportId = $this->insertStudentReport($context, array_merge([
            'estado' => 'APROBADO',
            'fecha_reporte' => $registeredAt,
            'fecha_revision' => $registeredAt,
            'id_usuario_revisor' => $context['id_usuario'],
        ], $reportOverrides));

        $recordId = DB::table('central_riesgo')->insertGetId([
            'id_reporte_est' => $reportId,
            'id_estudiante' => $reportOverrides['id_estudiante'] ?? $context['id_estudiante'],
            'fecha_registro' => $registeredAt,
        ], 'id_cr_estudiante');

        return ['id_reporte_est' => $reportId, 'id_cr_estudiante' => $recordId];
    }

    /** Reporte PENDIENTE sin evidencia ni intento, salvo lo que se indique en $overrides. */
    protected function insertStudentReport(array $context, array $overrides = []): int
    {
        return DB::table('reporte_estudiante')->insertGetId(array_merge([
            'id_estudiante' => $context['id_estudiante'],
            'id_examen' => $context['id_examen'],
            'id_tipo_falta' => $context['id_tipo_falta'],
            'id_usuario_reportante' => $context['id_usuario'],
            'evidencia_url' => null,
            'evidencia_public_id' => null,
            'estado' => 'PENDIENTE',
        ], $overrides), 'id_reporte_est');
    }
}
