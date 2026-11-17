<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Exceptions\Exams\ExamTimerLimitException;
use App\Models\Exam;
use App\Services\Security\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Persiste la cuenta de resolución sin alterar el ciclo de vida del examen. */
class ExamTimerService
{
    private ExamTimingService $timing;
    private AuditLogService $auditLog;

    public function __construct(ExamTimingService $timing, AuditLogService $auditLog)
    {
        $this->timing = $timing;
        $this->auditLog = $auditLog;
    }

    public function listInProgress(string $teacherId): Collection
    {
        return Exam::query()
            ->with(['subject:id_materia,nombre', 'career:id_carrera,nombre'])
            ->where('id_usuario_docente', $teacherId)
            ->where('estado', Exam::EN_CURSO)
            ->orderBy('id_examen')
            ->get();
    }

    public function assertOwner(Exam $exam, string $teacherId): void
    {
        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new ExamOwnershipException('Solo el docente responsable del examen puede controlar su tiempo');
        }
    }

    public function save(Exam $exam, string $teacherId, int $minutes, bool $adjust): Exam
    {
        return DB::transaction(function () use ($exam, $teacherId, $minutes, $adjust): Exam {
            $locked = Exam::query()->whereKey($exam->getKey())->lockForUpdate()->firstOrFail();
            $this->assertOwner($locked, $teacherId);

            if ($locked->estado !== Exam::EN_CURSO) {
                throw new ExamStateException('Solo puede controlar el tiempo de un examen en curso.');
            }

            $startedAt = $locked->cronometro_iniciado_en;
            if ($startedAt !== null && ! $adjust) {
                if (! $startedAt->copy()->addMinutes($minutes)->equalTo($locked->cronometro_vence_en)) {
                    throw new ExamStateException('El cronómetro ya fue iniciado con otros minutos.');
                }

                return $locked;
            }
            if ($startedAt === null && $adjust) {
                throw new ExamStateException('Primero debe iniciar el cronómetro.');
            }
            if ((int) $locked->duracion <= 0) {
                throw ValidationException::withMessages(['minutos' => 'El examen no tiene una duración válida.']);
            }

            $now = now('UTC')->setMicrosecond(0);
            $limit = $this->timing->resolutionLimit($locked);
            $startedAt = $startedAt ?? $now;
            $endsAt = $startedAt->copy()->addMinutes($minutes);
            if ($now->greaterThanOrEqualTo($limit) || $endsAt->greaterThan($limit)) {
                throw new ExamTimerLimitException();
            }

            $before = $locked->only(['id_examen', 'cronometro_iniciado_en', 'cronometro_vence_en']);
            // Enviar el desfase explícito evita depender de la zona de la conexión PostgreSQL.
            Exam::query()->whereKey($locked->getKey())->update([
                'cronometro_iniciado_en' => $startedAt->toIso8601String(),
                'cronometro_vence_en' => $endsAt->toIso8601String(),
            ]);

            $locked->refresh();
            $this->auditLog->registrar(
                $adjust ? 'AJUSTAR_CRONOMETRO' : 'INICIAR_CRONOMETRO',
                'examen',
                $before,
                $locked->only(['id_examen', 'cronometro_iniciado_en', 'cronometro_vence_en']),
                $teacherId
            );

            return $locked;
        });
    }
}
