<?php

namespace Database\Seeders\TestData;

use App\Support\ReportStatus;
use App\Support\SisCode;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Intentos de ingreso rechazados, reportes de estudiantes y central de riesgo de prueba (HU-042, HU-045
 * y HU-046). Corre después de ExamTestDataSeeder.
 *
 * Los exámenes 9807 (finalizado) y 9805 (en ingreso) son del docente fijo, que revisa todos los
 * reportes; los controla ASSISTANT_1/2 y ellos son quienes reportan. Para que los estudiantes
 * pertenezcan a esos exámenes se suma a José Pérez a los grupos 9401 y 9407 y se habilita a los
 * auxiliares en ambos: todo eso vive aquí, no en GroupTestDataSeeder ni en ExamTestDataSeeder.
 *
 * Los tipos de falta se resuelven por nombre (IncidentTypeSeeder, que solo inserta los que faltan):
 * su id depende de la base. Cada reporte con evidencia apunta a evidencias/reportes/<id>.<ext> del
 * disco público, donde se deja un archivo mínimo si el path está libre; nunca se pisa un archivo.
 *
 * Idempotente: upsert por clave primaria. La central se reconstruye: la fila de un reporte del
 * fixture que no sea una de las del fixture se elimina, así un reporte aprobado a mano y vuelto a
 * sembrar como PENDIENTE no queda con fila en la central.
 */
class RiskCenterTestDataSeeder extends Seeder
{
    private const ENROLLMENT_DATE = '2026-08-03';

    private const ENABLEMENT_DATE = '2026-09-01 08:00:00-04';

    private const EXAM_FINISHED = 9807;

    private const EXAM_ENTRY = 9805;

    private const CLASSROOM_FINISHED = 9601;

    private const CLASSROOM_ENTRY = 9604;

    private const EVIDENCE_DIRECTORY = 'evidencias/reportes';

    /** Reportes del fixture que tienen evidencia: id => extensión del archivo. */
    private const EVIDENCE_EXTENSIONS = [
        9921 => 'png',
        9923 => 'pdf',
        9925 => 'png',
        9926 => 'png',
        9928 => 'pdf',
    ];

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5E'
        . 'rkJggg==';

    public function run()
    {
        LocalDatabaseGuard::assertLocal(static::class);

        $this->call(IncidentTypeSeeder::class);

        $this->seedStudentAndEnrollments();
        $this->seedExamAssistants();
        $this->seedEntryAttempts();
        $this->seedReportsAndRiskCenter();
        $this->seedEvidencePlaceholders();
    }

    private function seedStudentAndEnrollments(): void
    {
        DB::table('estudiante')->upsert([[
            'id_estudiante' => Ids::STUDENT_JOSE_PEREZ,
            'cod_sis' => SisCode::normalize('202150025'),
            'ci' => '7100025',
            'nombre' => 'José',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'Mamani',
            'correo_institucional' => '202150025@est.sciem.test',
            'telefono' => null,
            'estado' => 'ACTIVO',
        ]], ['id_estudiante']);

        DB::table('grupo_estudiante')->upsert([
            $this->enrollment(Ids::GROUP_CALCULUS_SYS_1),
            $this->enrollment(Ids::GROUP_PROGRAMMING_SYS_2),
        ], ['id_grupo', 'id_estudiante']);
    }

    private function enrollment(int $groupId): array
    {
        return [
            'id_grupo' => $groupId,
            'id_estudiante' => Ids::STUDENT_JOSE_PEREZ,
            'fecha_inscripcion' => self::ENROLLMENT_DATE,
            'estado' => 'ACTIVO',
        ];
    }

    private function seedExamAssistants(): void
    {
        $classrooms = [self::EXAM_FINISHED => self::CLASSROOM_FINISHED, self::EXAM_ENTRY => self::CLASSROOM_ENTRY];
        $rows = [];

        foreach ($classrooms as $examId => $classroomId) {
            foreach ([Ids::ASSISTANT_1, Ids::ASSISTANT_2] as $assistantId) {
                $rows[] = [
                    'id_examen' => $examId,
                    'id_usuario' => $assistantId,
                    'id_usuario_docente_habilita' => Ids::TEACHER_FIXED,
                    'fecha_habilitacion' => self::ENABLEMENT_DATE,
                    'id_ambiente' => $classroomId,
                ];
            }
        }

        DB::table('examen_auxiliar')->upsert($rows, ['id_examen', 'id_usuario']);
    }

    private function seedEntryAttempts(): void
    {
        $finished = [self::EXAM_FINISHED, self::CLASSROOM_FINISHED];
        $entry = [self::EXAM_ENTRY, self::CLASSROOM_ENTRY];
        $jose = Ids::STUDENT_JOSE_PEREZ;
        [$a1, $a2] = [Ids::ASSISTANT_1, Ids::ASSISTANT_2];

        DB::table('intento_ingreso')->upsert([
            // Los nueve primeros originan un reporte.
            $this->attempt(9901, $finished, $jose, $a1, 'DOCUMENTO_NO_VALIDO', '2026-09-10 08:12:00-04',
                'Cédula con fotografía ilegible.'),
            $this->attempt(9902, $entry, $jose, $a2, 'IDENTIDAD_DUDOSA', '2026-09-21 09:05:00-04',
                'No coincide con la fotografía del carnet.'),
            $this->attempt(9903, $finished, 9503, $a1, 'IDENTIDAD_DUDOSA', '2026-09-10 08:20:00-04',
                'El rostro no coincide con el documento.'),
            $this->attempt(9904, $entry, 9503, $a2, 'DECISION_CONTROLADOR', '2026-09-21 09:08:00-04',
                'Ingresó con el celular encendido.'),
            $this->attempt(9905, $finished, 9502, $a1, 'DOCUMENTO_NO_VALIDO', '2026-09-10 08:25:00-04',
                'Documento vencido.'),
            $this->attempt(9906, $finished, 9501, $a2, 'OTRO', '2026-09-10 08:31:00-04',
                'Intentó pasar material impreso.'),
            $this->attempt(9907, $entry, 9504, $a1, 'DECISION_CONTROLADOR', '2026-09-21 09:12:00-04',
                'Discutió con el controlador.'),
            $this->attempt(9908, $finished, 9505, $a1, 'DOCUMENTO_NO_VALIDO', '2026-09-10 08:40:00-04',
                'Presentó una fotocopia.'),
            $this->attempt(9909, $finished, 9506, $a2, 'OTRO', '2026-09-10 08:44:00-04',
                'Se negó a mostrar su documento.'),
            // Estudiante identificado y rechazado, sin reporte todavía: el caso de entrada de HU-042.
            $this->attempt(9910, $finished, 9504, $a1, 'IDENTIDAD_DUDOSA', '2026-09-10 08:50:00-04',
                'Pendiente de que el controlador decida si reporta.'),
            // El estudiante no figura en el sistema: no hay id_estudiante ni reporte posible.
            $this->attempt(9911, $entry, null, $a2, 'NO_ENCONTRADO', '2026-09-21 09:20:00-04',
                null, '202199999', '9999999'),
        ], ['id_intento']);
    }

    /**
     * @param  array{0: int, 1: int}  $examAndClassroom
     */
    private function attempt(
        int $id,
        array $examAndClassroom,
        ?int $studentId,
        string $controllerId,
        string $reason,
        string $registeredAt,
        ?string $observation,
        ?string $sisCode = null,
        ?string $presentedCi = null
    ): array {
        $student = $studentId === null ? null : DB::table('estudiante')->where('id_estudiante', $studentId)->first();

        return [
            'id_intento' => $id,
            'id_examen' => $examAndClassroom[0],
            'id_estudiante' => $studentId,
            'cod_sis' => $sisCode ?? ($student->cod_sis ?? null),
            'ci_presentado' => $presentedCi ?? ($student->ci ?? null),
            'id_ambiente' => $examAndClassroom[1],
            'id_usuario_controlador' => $controllerId,
            'motivo' => $reason,
            'observacion' => $observation,
            'registrado_en' => $registeredAt,
        ];
    }

    private function seedReportsAndRiskCenter(): void
    {
        $types = DB::table('tipo_falta')->pluck('id_falta', 'nombre');
        $copy = $types['Copia en examen'];
        $impersonation = $types['Suplantación de identidad'];
        $material = $types['Material no permitido'];
        $conduct = $types['Conducta indebida'];
        $teacher = Ids::TEACHER_FIXED;
        [$a1, $a2] = [Ids::ASSISTANT_1, Ids::ASSISTANT_2];

        $jose = Ids::STUDENT_JOSE_PEREZ;
        [$finished, $entry] = [self::EXAM_FINISHED, self::EXAM_ENTRY];
        $approved = ReportStatus::APPROVED;

        $reports = [
            // Aprobados: cada uno tiene su fila en la central (fecha_registro = fecha_revision).
            $this->report(9921, $jose, $finished, $copy, $a1, 9901, 'Copiaba respuestas de su compañero.',
                '2026-09-10 08:40:00-04', $approved, '2026-09-10 16:00:00-04', $teacher,
                'Confirmado con el controlador.'),
            $this->report(9923, 9503, $finished, $impersonation, $a1, 9903, 'Rindió en nombre de otra persona.',
                '2026-09-10 08:50:00-04', $approved, '2026-09-12 11:00:00-04', $teacher,
                'La identidad no se pudo acreditar.'),
            $this->report(9925, 9502, $finished, $copy, $a1, 9905, 'Usaba apuntes durante el examen.',
                '2026-09-10 09:05:00-04', $approved, '2026-09-15 09:30:00-04', $teacher,
                'Evidencia suficiente.'),
            $this->report(9924, 9503, $entry, $conduct, $a2, 9904, 'Alteró el orden del ingreso.',
                '2026-09-21 09:25:00-04', $approved, '2026-09-21 18:00:00-04', $teacher),
            $this->report(9922, $jose, $entry, $material, $a2, 9902, 'Ingresó con un dispositivo no permitido.',
                '2026-09-21 09:20:00-04', $approved, '2026-09-22 08:00:00-04', $teacher, 'Reincidente.'),
            // Pendientes: uno con evidencia y otro sin.
            $this->report(9926, 9501, $finished, $material, $a2, 9906, 'Material impreso entre sus útiles.',
                '2026-09-10 09:10:00-04', ReportStatus::PENDING),
            $this->report(9927, 9504, $entry, $conduct, $a1, 9907, 'Se negó a seguir las indicaciones.',
                '2026-09-21 09:30:00-04', ReportStatus::PENDING),
            // Rechazados: uno con evidencia y otro sin.
            $this->report(9928, 9505, $finished, $impersonation, $a1, 9908, 'Sospecha de documento falso.',
                '2026-09-10 09:15:00-04', ReportStatus::REJECTED, '2026-09-11 10:00:00-04', $teacher,
                'La fotocopia era legible y coincide.'),
            $this->report(9929, 9506, $finished, $conduct, $a2, 9909, 'Se negó a mostrar su documento.',
                '2026-09-10 09:20:00-04', ReportStatus::REJECTED, '2026-09-11 10:30:00-04', $teacher,
                'Sin evidencia que lo respalde.'),
        ];

        DB::table('reporte_estudiante')->upsert($reports, ['id_reporte_est']);

        $riskCenter = [
            $this->riskCenterRow(9941, 9921, $jose, '2026-09-10 16:00:00-04'),
            $this->riskCenterRow(9942, 9923, 9503, '2026-09-12 11:00:00-04'),
            $this->riskCenterRow(9943, 9925, 9502, '2026-09-15 09:30:00-04'),
            $this->riskCenterRow(9944, 9924, 9503, '2026-09-21 18:00:00-04'),
            $this->riskCenterRow(9945, 9922, $jose, '2026-09-22 08:00:00-04'),
        ];

        DB::table('central_riesgo')
            ->whereIn('id_reporte_est', array_column($reports, 'id_reporte_est'))
            ->whereNotIn('id_cr_estudiante', array_column($riskCenter, 'id_cr_estudiante'))
            ->delete();

        DB::table('central_riesgo')->upsert($riskCenter, ['id_cr_estudiante']);
    }

    private function riskCenterRow(int $id, int $reportId, int $studentId, string $registeredAt): array
    {
        return [
            'id_cr_estudiante' => $id,
            'id_reporte_est' => $reportId,
            'id_estudiante' => $studentId,
            'fecha_registro' => $registeredAt,
        ];
    }

    private function report(
        int $id,
        int $studentId,
        int $examId,
        int $typeId,
        string $reporterId,
        int $attemptId,
        string $description,
        string $reportedAt,
        string $status,
        ?string $reviewedAt = null,
        ?string $reviewerId = null,
        ?string $reviewNote = null
    ): array {
        $extension = self::EVIDENCE_EXTENSIONS[$id] ?? null;
        $path = $extension === null ? null : $this->evidencePath($id, $extension);

        return [
            'id_reporte_est' => $id,
            'id_estudiante' => $studentId,
            'id_examen' => $examId,
            'id_tipo_falta' => $typeId,
            'id_usuario_reportante' => $reporterId,
            'id_intento' => $attemptId,
            'descripcion' => $description,
            'evidencia_url' => $path === null ? null : Storage::disk('public')->url($path),
            'evidencia_public_id' => $path,
            'fecha_reporte' => $reportedAt,
            'estado' => $status,
            'fecha_revision' => $reviewedAt,
            'observacion_revision' => $reviewNote,
            'id_usuario_revisor' => $reviewerId,
        ];
    }

    private function evidencePath(int $reportId, string $extension): string
    {
        return self::EVIDENCE_DIRECTORY . "/{$reportId}.{$extension}";
    }

    /** Deja un archivo mínimo donde el reporte lo espera, para que el enlace no dé 404. Nunca pisa uno existente. */
    private function seedEvidencePlaceholders(): void
    {
        $disk = Storage::disk('public');

        foreach (self::EVIDENCE_EXTENSIONS as $reportId => $extension) {
            $path = $this->evidencePath($reportId, $extension);

            if (! $disk->exists($path)) {
                $disk->put($path, $extension === 'png' ? base64_decode(self::PNG_BASE64) : $this->pdfPlaceholder());
            }
        }
    }

    /** PDF de una página en blanco, con su tabla xref calculada para que cualquier visor lo abra sin reparar. */
    private function pdfPlaceholder(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
