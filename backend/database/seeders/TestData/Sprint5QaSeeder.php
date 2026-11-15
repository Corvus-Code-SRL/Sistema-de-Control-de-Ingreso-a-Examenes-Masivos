<?php

namespace Database\Seeders\TestData;

use App\Support\ReportStatus;
use App\Support\SisCode;
use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Escenarios de QA del Sprint 5. Hoy cubre HU-062 (rectificar un registro de la Central de Riesgo).
 *
 * Primero carga TestDataSeeder (cuentas, catálogo, grupos, exámenes y Central de Riesgo base) y después
 * agrega los estudiantes y registros que hacen probable la búsqueda y la rectificación. Se ejecuta a mano,
 * solo en una base local:
 *
 *     php artisan db:seed --class="Database\Seeders\TestData\Sprint5QaSeeder"
 *
 * Escenarios:
 *   - María Pérez Gómez (con tilde) y Luis Perez Soliz (sin tilde) comparten el apellido: buscar «perez»
 *     devuelve a ambos más a José Pérez Mamani, del fixture base. María tiene un registro sin rectificar
 *     y Luis no tiene ninguno.
 *   - Carla Condori Vega tiene dos registros confirmados: uno ya rectificado una vez (de «Material no
 *     permitido» a «Copia en examen», con motivo y autor) y otro sin rectificar.
 *   - Daniel Rojas Mendoza no tiene registros: sirve para el mensaje de estudiante sin registros.
 *
 * Volver a correrlo devuelve los escenarios a su estado inicial: se reescriben los reportes y registros de
 * la central, y se borran y recrean las versiones de estos tres registros (si QA los rectificó, se pierden).
 * La bitácora no se toca.
 *
 * Ids propios en el rango 9000–9999, sin pisar los de TestDataIds ni los de Sprint3QaSeeder.
 */
class Sprint5QaSeeder extends Seeder
{
    // estudiante.id_estudiante
    public const STUDENT_MARIA_PEREZ = 9700;
    public const STUDENT_LUIS_PEREZ = 9701;
    public const STUDENT_CARLA_CONDORI = 9702;
    public const STUDENT_DANIEL_ROJAS = 9703;

    // reporte_estudiante.id_reporte_est
    public const REPORT_CARLA_RECTIFIED = 9950;
    public const REPORT_CARLA_ORIGINAL = 9951;
    public const REPORT_MARIA = 9952;

    // central_riesgo.id_cr_estudiante
    public const RECORD_CARLA_RECTIFIED = 9961;
    public const RECORD_CARLA_ORIGINAL = 9962;
    public const RECORD_MARIA = 9963;

    // central_riesgo_version.id_version
    public const VERSION_CARLA_FIRST = 9971;
    public const VERSION_CARLA_CURRENT = 9972;

    private const EXAM_FINISHED = 9807;

    private const EXAM_ENTRY = 9805;

    public function run()
    {
        LocalDatabaseGuard::assertLocal(static::class);

        // Base compartida: TestDataSeeder valida el entorno (local, PostgreSQL, nunca sciem_test).
        $this->call(TestDataSeeder::class);

        DB::transaction(function () {
            $this->seedStudents();
            $this->seedRecords();
            $this->seedRectification();
        });
    }

    private function seedStudents(): void
    {
        DB::table('estudiante')->upsert([
            $this->student(self::STUDENT_MARIA_PEREZ, '202170101', '7400101', 'María', 'Pérez', 'Gómez'),
            $this->student(self::STUDENT_LUIS_PEREZ, '202170102', '7400102', 'Luis', 'Perez', 'Soliz'),
            $this->student(self::STUDENT_CARLA_CONDORI, '202170103', '7400103', 'Carla', 'Condori', 'Vega'),
            $this->student(self::STUDENT_DANIEL_ROJAS, '202170104', '7400104', 'Daniel', 'Rojas', 'Mendoza'),
        ], ['id_estudiante']);
    }

    /** @return array<string, mixed> */
    private function student(int $id, string $sis, string $ci, string $name, string $paternal, string $maternal): array
    {
        return [
            'id_estudiante' => $id,
            'cod_sis' => SisCode::normalize($sis),
            'ci' => $ci,
            'nombre' => $name,
            'apellido_paterno' => $paternal,
            'apellido_materno' => $maternal,
            'correo_institucional' => $sis . '@est.sciem.test',
            'telefono' => null,
            'estado' => 'ACTIVO',
        ];
    }

    private function seedRecords(): void
    {
        $types = DB::table('tipo_falta')->pluck('id_falta', 'nombre');
        $teacher = Ids::TEACHER_FIXED;
        $assistant = Ids::ASSISTANT_1;
        $approved = ReportStatus::APPROVED;

        $reports = [
            $this->report(self::REPORT_CARLA_RECTIFIED, self::STUDENT_CARLA_CONDORI, self::EXAM_FINISHED,
                $types['Material no permitido'], $assistant, 'Tenía material impreso entre sus útiles.',
                '2026-09-10 08:30:00-04', $approved, '2026-09-11 15:00:00-04', $teacher),
            $this->report(self::REPORT_CARLA_ORIGINAL, self::STUDENT_CARLA_CONDORI, self::EXAM_ENTRY,
                $types['Conducta indebida'], $assistant, 'Interrumpió el ingreso de otros estudiantes.',
                '2026-09-21 09:10:00-04', $approved, '2026-09-22 10:00:00-04', $teacher),
            $this->report(self::REPORT_MARIA, self::STUDENT_MARIA_PEREZ, self::EXAM_FINISHED,
                $types['Copia en examen'], $assistant, 'Copiaba respuestas de su compañero.',
                '2026-09-10 08:45:00-04', $approved, '2026-09-12 09:00:00-04', $teacher),
        ];

        DB::table('reporte_estudiante')->upsert($reports, ['id_reporte_est']);

        DB::table('central_riesgo')->upsert([
            $this->record(self::RECORD_CARLA_RECTIFIED, self::REPORT_CARLA_RECTIFIED, self::STUDENT_CARLA_CONDORI,
                '2026-09-11 15:00:00-04'),
            $this->record(self::RECORD_CARLA_ORIGINAL, self::REPORT_CARLA_ORIGINAL, self::STUDENT_CARLA_CONDORI,
                '2026-09-22 10:00:00-04'),
            $this->record(self::RECORD_MARIA, self::REPORT_MARIA, self::STUDENT_MARIA_PEREZ, '2026-09-12 09:00:00-04'),
        ], ['id_cr_estudiante']);
    }

    /** @return array<string, mixed> */
    private function report(
        int $id,
        int $studentId,
        int $examId,
        int $typeId,
        string $reporterId,
        string $description,
        string $reportedAt,
        string $status,
        string $reviewedAt,
        string $reviewerId
    ): array {
        return [
            'id_reporte_est' => $id,
            'id_estudiante' => $studentId,
            'id_examen' => $examId,
            'id_tipo_falta' => $typeId,
            'id_usuario_reportante' => $reporterId,
            'id_intento' => null,
            'descripcion' => $description,
            'evidencia_url' => null,
            'evidencia_public_id' => null,
            'fecha_reporte' => $reportedAt,
            'estado' => $status,
            'fecha_revision' => $reviewedAt,
            'observacion_revision' => 'Confirmado.',
            'id_usuario_revisor' => $reviewerId,
        ];
    }

    /** @return array<string, mixed> */
    private function record(int $id, int $reportId, int $studentId, string $registeredAt): array
    {
        return [
            'id_cr_estudiante' => $id,
            'id_reporte_est' => $reportId,
            'id_estudiante' => $studentId,
            'fecha_registro' => $registeredAt,
        ];
    }

    /** Deja el registro de Carla rectificado una vez: versión 1 con los valores originales y versión 2 vigente. */
    private function seedRectification(): void
    {
        $types = DB::table('tipo_falta')->pluck('id_falta', 'nombre');

        DB::table('central_riesgo_version')
            ->whereIn('id_cr_estudiante', [
                self::RECORD_CARLA_RECTIFIED,
                self::RECORD_CARLA_ORIGINAL,
                self::RECORD_MARIA,
            ])
            ->delete();

        DB::table('central_riesgo_version')->insert([
            [
                'id_version' => self::VERSION_CARLA_FIRST,
                'id_cr_estudiante' => self::RECORD_CARLA_RECTIFIED,
                'numero_version' => 1,
                'id_tipo_falta' => $types['Material no permitido'],
                'descripcion' => 'Tenía material impreso entre sus útiles.',
                'motivo' => null,
                'id_usuario_autor' => Ids::TEACHER_FIXED,
                'fecha_registro' => '2026-09-11 15:00:00-04',
                'es_vigente' => false,
            ],
            [
                'id_version' => self::VERSION_CARLA_CURRENT,
                'id_cr_estudiante' => self::RECORD_CARLA_RECTIFIED,
                'numero_version' => 2,
                'id_tipo_falta' => $types['Copia en examen'],
                'descripcion' => 'El material impreso contenía las respuestas del examen y las copiaba.',
                'motivo' => 'La evidencia confirma que se trataba de copia y no de material olvidado.',
                'id_usuario_autor' => Ids::ADMINISTRATOR,
                'fecha_registro' => '2026-10-05 10:00:00-04',
                'es_vigente' => true,
            ],
        ]);
    }
}
