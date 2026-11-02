<?php

namespace App\Services\Incidents;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\StudentReport;
use App\Models\User;
use App\Services\Exams\ExamExpulsionService;
use App\Services\Security\AuditLogService;
use App\Support\ReportStatus;
use App\Support\Storage\IncidentEvidenceStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Registra reporte, retiro opcional y bitácora de forma atómica. La revisión sigue en HU-045. */
class InProgressIncidentService
{
    public function __construct(
        private ExamSituationAccessService $access,
        private ExamExpulsionService $expulsions,
        private AuditLogService $audit,
        private IncidentEvidenceStorage $evidence
    ) {
    }

    public function report(
        int $examId,
        int $studentId,
        array $input,
        ?UploadedFile $file,
        User $actor
    ): StudentReport {
        $path = null;
        try {
            return DB::transaction(function () use ($examId, $studentId, $input, $file, $actor, &$path) {
                // Mismo orden de bloqueo que el cierre y los traslados: examen, luego asistencia.
                $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();
                $scope = $this->access->roomScope($exam, $actor);
                if ($exam->estado !== Exam::EN_CURSO) {
                    throw new ExamStateException('Solo puede reportar durante el examen en curso.');
                }
                $attendance = DB::table('examen_estudiante')->where('id_examen', $examId)
                    ->where('id_estudiante', $studentId)->lockForUpdate()->first();
                if ($attendance === null || $attendance->id_ambiente === null) {
                    throw new ExamStateException('El estudiante no tiene un ingreso registrado en este examen.');
                }
                $roomId = (int) $attendance->id_ambiente;
                $this->access->assertRoomAllowed($exam, $roomId, $scope);
                $this->expulsions->assertNotExpelled($attendance->expulsado_en);
                if (! in_array($attendance->estado_ingreso, ['INGRESO', 'CON_RETRASO'], true)) {
                    throw new ExamStateException('Solo puede reportar a un estudiante presente.');
                }
                if ($roomId !== (int) $input['id_ambiente']) {
                    throw new ExamStateException(
                        'El estudiante cambió de ambiente. Cierre y vuelva a abrir el reporte.'
                    );
                }
                $path = $file === null ? null : $this->evidence->store($file);
                $timestamp = now('UTC')->format('Y-m-d H:i:sP');
                $retire = (bool) $input['retirar'];
                $attributes = [
                    'id_examen' => $examId,
                    'id_estudiante' => $studentId,
                    'id_intento' => null,
                    'origen' => 'EN_CURSO',
                    'id_ambiente_hecho' => $roomId,
                    'retiro_examen' => $retire,
                    'id_tipo_falta' => $input['id_tipo_falta'],
                    'descripcion' => trim($input['descripcion']),
                    'id_usuario_reportante' => $actor->id_usuario,
                    'fecha_reporte' => $timestamp,
                    'estado' => ReportStatus::PENDING,
                    'evidencia_url' => $path,
                    'evidencia_public_id' => $path,
                ];
                $reportId = DB::table('reporte_estudiante')->insertGetId($attributes, 'id_reporte_est');
                if ($retire) {
                    DB::table('examen_estudiante')->where('id_examen', $examId)
                        ->where('id_estudiante', $studentId)->update(['expulsado_en' => $timestamp]);
                    $this->audit->registrar(
                        'EXPULSAR_ESTUDIANTE',
                        'examen_estudiante',
                        ['id_examen' => $examId, 'id_estudiante' => $studentId, 'expulsado_en' => null],
                        [
                            'id_examen' => $examId, 'id_estudiante' => $studentId,
                            'expulsado_en' => $timestamp, 'id_reporte_est' => (int) $reportId,
                        ],
                        $actor->id_usuario
                    );
                }
                $this->audit->registrar(
                    'CREAR',
                    'reporte_estudiante',
                    null,
                    array_merge($attributes, ['id_reporte_est' => (int) $reportId]),
                    $actor->id_usuario
                );

                return StudentReport::query()->findOrFail($reportId);
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                $this->evidence->delete($path);
            }
            throw $exception;
        }
    }
}
