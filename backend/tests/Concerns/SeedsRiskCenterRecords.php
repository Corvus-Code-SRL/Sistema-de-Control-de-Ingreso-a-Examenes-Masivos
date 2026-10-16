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
