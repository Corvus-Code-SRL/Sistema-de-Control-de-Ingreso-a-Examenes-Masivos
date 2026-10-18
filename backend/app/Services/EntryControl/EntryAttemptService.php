<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EntryAttemptService
{
    private VerificationService $verification;
    private AuditLogService $auditLog;

    public function __construct(VerificationService $verification, AuditLogService $auditLog)
    {
        $this->verification = $verification;
        $this->auditLog = $auditLog;
    }

    public function recordDenied(int $examId, array $input, User $actor, array $verdict): ?array
    {
        if ($verdict['autorizado'] || in_array($verdict['veredicto'], ['AULA_NO_ASIGNADA', 'AUXILIAR_EXAMEN'], true)) {
            return null;
        }

        return $this->insert($examId, $input, $actor, $verdict['veredicto'], null, $verdict);
    }

    public function reject(Exam $exam, array $input, User $actor): array
    {
        $verdict = $this->verification->verify((int) $exam->id_examen, $input, $actor);

        if (! $verdict['autorizado']) {
            return ['registrado' => false, 'veredicto' => $verdict['veredicto'], 'motivo' => $verdict['motivo']];
        }

        $attempt = $this->insert(
            (int) $exam->id_examen,
            $input,
            $actor,
            $input['motivo'],
            $input['observacion'] ?? null,
            $verdict
        );

        return array_merge($attempt, ['registrado' => true, 'motivo' => $input['motivo']]);
    }

    private function insert(
        int $examId,
        array $input,
        User $actor,
        string $reason,
        ?string $observation,
        array $verdict
    ): array {
        return DB::transaction(function () use ($examId, $input, $actor, $reason, $observation, $verdict): array {
            $studentId = $verdict['estudiante']['id_estudiante'] ?? null;
            $registeredAt = Carbon::now('UTC')->startOfSecond();
            $id = (int) DB::table('intento_ingreso')->insertGetId([
                'id_examen' => $examId,
                'id_estudiante' => $studentId,
                'cod_sis' => $input['cod_sis'] ?? ($verdict['estudiante']['cod_sis'] ?? null),
                'ci_presentado' => $input['ci'] ?? null,
                'id_ambiente' => (int) $input['id_ambiente'],
                'id_usuario_controlador' => $actor->id_usuario,
                'motivo' => $reason,
                'observacion' => $observation,
                'registrado_en' => $registeredAt->format('Y-m-d H:i:sP'),
            ], 'id_intento');

            $this->auditLog->registrar('CREAR', 'intento_ingreso', null, [
                'id_intento' => $id,
                'id_examen' => $examId,
                'id_estudiante' => $studentId,
                'motivo' => $reason,
            ], $actor->id_usuario);

            return ['id_intento' => $id, 'registrado_en' => $registeredAt->toIso8601String()];
        });
    }
}
