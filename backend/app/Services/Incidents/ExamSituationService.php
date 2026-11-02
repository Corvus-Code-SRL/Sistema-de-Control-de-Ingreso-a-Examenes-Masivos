<?php

namespace App\Services\Incidents;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\ExamSituation;
use App\Models\User;
use App\Services\Security\AuditLogService;
use App\Services\Exams\ExamExpulsionService;
use App\Support\ExamSituationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Guarda situación, cambio de ambiente y bitácora dentro de la misma transacción. */
class ExamSituationService
{
    private ExamSituationAccessService $access;
    private AuditLogService $audit;
    private ExamExpulsionService $expulsions;

    public function __construct(
        ExamSituationAccessService $access,
        AuditLogService $audit,
        ExamExpulsionService $expulsions
    ) {
        $this->access = $access;
        $this->audit = $audit;
        $this->expulsions = $expulsions;
    }

    /** $input contiene exclusivamente los datos validados por StoreExamSituationRequest. */
    public function register(int $examId, int $studentId, array $input, User $actor): ExamSituation
    {
        return DB::transaction(function () use ($examId, $studentId, $input, $actor): ExamSituation {
            $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();
            $roomScope = $this->access->roomScope($exam, $actor);

            if ($exam->estado !== Exam::EN_CURSO) {
                throw new ExamStateException('Solo puede registrar situaciones durante el examen en curso.');
            }

            $attendance = DB::table('examen_estudiante')
                ->where('id_examen', $examId)
                ->where('id_estudiante', $studentId)
                ->lockForUpdate()
                ->first();

            if ($attendance === null) {
                throw ValidationException::withMessages([
                    'estudiante' => ['El estudiante no pertenece a este examen.'],
                ]);
            }

            if ($attendance->id_ambiente === null) {
                throw new ExamStateException('El estudiante no tiene un ambiente asignado.');
            }

            $sourceRoomId = (int) $attendance->id_ambiente;
            $this->access->assertRoomAllowed($exam, $sourceRoomId, $roomScope);
            $this->expulsions->assertNotExpelled($attendance->expulsado_en);

            if (! in_array($attendance->estado_ingreso, ['INGRESO', 'CON_RETRASO'], true)) {
                throw new ExamStateException('Solo puede registrar situaciones de estudiantes presentes.');
            }

            $destinationRoomId = null;
            $action = 'OBSERVAR_IDENTIDAD';

            if ($input['tipo'] === ExamSituationType::ROOM_CHANGE) {
                $destinationRoomId = (int) $input['id_ambiente_destino'];
                $validDestination = DB::table('examen_ambiente')
                    ->where('id_examen', $examId)
                    ->where('id_ambiente', $destinationRoomId)
                    ->exists();

                if (! $validDestination || $destinationRoomId === $sourceRoomId) {
                    throw ValidationException::withMessages([
                        'id_ambiente_destino' => ['Seleccione otro ambiente de este examen.'],
                    ]);
                }

                DB::table('examen_estudiante')
                    ->where('id_examen', $examId)
                    ->where('id_estudiante', $studentId)
                    ->update(['id_ambiente' => $destinationRoomId]);

                $action = 'CAMBIAR_AMBIENTE';
            }

            $attributes = [
                'id_examen' => $examId,
                'id_estudiante' => $studentId,
                'tipo' => $input['tipo'],
                'id_ambiente_origen' => $sourceRoomId,
                'id_ambiente_destino' => $destinationRoomId,
                'motivo' => $input['motivo'],
                'registrado_en' => now('UTC')->format('Y-m-d H:i:sP'),
                'id_usuario_registrante' => $actor->id_usuario,
            ];

            // El timestamp se envía con offset explícito, independientemente de la zona de la conexión.
            $situationId = DB::table('situacion_examen')->insertGetId($attributes, 'id_situacion');

            $this->audit->registrar(
                $action,
                'situacion_examen',
                null,
                array_merge($attributes, ['id_situacion' => (int) $situationId]),
                $actor->id_usuario
            );

            return ExamSituation::query()
                ->with(['student', 'originRoom', 'destinationRoom', 'reporter'])
                ->findOrFail($situationId);
        });
    }
}
