<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EntryAttemptService
{
    private VerificationService $verification;

    public function __construct(VerificationService $verification)
    {
        $this->verification = $verification;
    }

    public function recordDenied(int $examId, array $input, User $actor, array $verdict): void
    {
        if ($verdict['autorizado'] || $verdict['veredicto'] === 'AULA_NO_ASIGNADA') {
            return;
        }

        $this->insert($examId, $input, $actor, $verdict['veredicto'], null, $verdict);
    }

    public function reject(Exam $exam, array $input, User $actor): array
    {
        $verdict = $this->verification->verify((int) $exam->id_examen, $input, $actor);

        if (! $verdict['autorizado']) {
            return ['registrado' => false, 'veredicto' => $verdict['veredicto'], 'motivo' => $verdict['motivo']];
        }

        $id = $this->insert(
            (int) $exam->id_examen,
            $input,
            $actor,
            $input['motivo'],
            $input['observacion'] ?? null,
            $verdict
        );

        return ['registrado' => true, 'id_intento' => $id, 'motivo' => $input['motivo']];
    }

    private function insert(
        int $examId,
        array $input,
        User $actor,
        string $reason,
        ?string $observation,
        array $verdict
    ): int {
        return (int) DB::table('intento_ingreso')->insertGetId([
            'id_examen' => $examId,
            'id_estudiante' => $verdict['estudiante']['id_estudiante'] ?? null,
            'cod_sis' => $input['cod_sis'] ?? ($verdict['estudiante']['cod_sis'] ?? null),
            'ci_presentado' => $input['ci'] ?? null,
            'id_ambiente' => (int) $input['id_ambiente'],
            'id_usuario_controlador' => $actor->id_usuario,
            'motivo' => $reason,
            'observacion' => $observation,
            'registrado_en' => Carbon::now('UTC')->format('Y-m-d H:i:sP'),
        ], 'id_intento');
    }
}
