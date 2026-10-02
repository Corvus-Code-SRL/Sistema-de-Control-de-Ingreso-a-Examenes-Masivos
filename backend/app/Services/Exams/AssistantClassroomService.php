<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamAssistantNotFoundException;
use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Services\Academic\SubjectCatalogService;
use App\Services\Security\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Asigna a cada auxiliar habilitado el ambiente del examen donde controlará el ingreso (HU-09).
 *
 * La asignación y la reasignación solo se permiten mientras el examen está PROGRAMADO:
 * desde que se abre el control de ingreso quedan fijas, igual que la información general
 * del examen (HU-24).
 */
class AssistantClassroomService
{
    /** Estados en los que el auxiliar todavía tiene un examen por controlar. */
    private const ACTIVE_STATES = [Exam::PROGRAMADO, Exam::EN_INGRESO, Exam::EN_CURSO];

    private SubjectCatalogService $subjectCatalog;

    private AuditLogService $auditLog;

    public function __construct(SubjectCatalogService $subjectCatalog, AuditLogService $auditLog)
    {
        $this->subjectCatalog = $subjectCatalog;
        $this->auditLog = $auditLog;
    }

    /**
     * Auxiliares habilitados del examen con su ambiente, los ambientes del examen, su estado
     * y si todavía se pueden cambiar. Todo en cuatro consultas, sin importar cuántos auxiliares haya.
     *
     * @return array{estado: string, editable: bool, classrooms: Collection, assistants: Collection}
     */
    public function listForExam(Exam $exam): array
    {
        $this->assertOwnedBy($exam, $this->currentTeacherId());

        $assistants = $exam->assistants()
            ->with(['assistant', 'classroom'])
            ->get()
            ->sortBy(fn (ExamAssistant $assistant) => $assistant->assistant->nombre_completo)
            ->values();

        return [
            'estado'     => $exam->estado,
            'editable'   => $exam->estado === Exam::PROGRAMADO,
            'classrooms' => $exam->classrooms()->orderBy('nro_aula')->get(),
            'assistants' => $assistants,
        ];
    }

    /**
     * Asigna o reasigna el ambiente de un auxiliar. Las validaciones van en este orden:
     * docente propietario (403), examen programado (409), auxiliar habilitado (404) y
     * ambiente del examen (422). Si alguna falla, la asignación anterior se conserva.
     */
    public function assign(Exam $exam, string $userId, int $classroomId): ExamAssistant
    {
        $teacherId = $this->currentTeacherId();

        return DB::transaction(function () use ($exam, $userId, $classroomId, $teacherId) {
            // Serializa esta operación con las demás que bloquean el examen (edición y cancelación).
            $exam = Exam::query()->whereKey($exam->id_examen)->lockForUpdate()->firstOrFail();

            $this->assertOwnedBy($exam, $teacherId);

            if ($exam->estado !== Exam::PROGRAMADO) {
                throw new ExamStateException($this->notEditableMessage($exam->estado));
            }

            $assistant = ExamAssistant::forExamAndAssistant($exam->id_examen, $userId)
                ->lockForUpdate()
                ->first();

            if ($assistant === null) {
                throw new ExamAssistantNotFoundException();
            }

            $belongsToExam = $exam->classrooms()
                ->where('ambiente.id_ambiente', $classroomId)
                ->exists();

            if (! $belongsToExam) {
                throw ValidationException::withMessages([
                    'id_ambiente' => 'El ambiente seleccionado no pertenece a este examen.',
                ]);
            }

            if ($assistant->id_ambiente !== $classroomId) {
                ExamAssistant::forExamAndAssistant($exam->id_examen, $userId)
                    ->update(['id_ambiente' => $classroomId]);

                $this->auditLog->registrar(
                    'MODIFICAR',
                    'examen_auxiliar',
                    $this->auditValues($exam->id_examen, $userId, $assistant->id_ambiente),
                    $this->auditValues($exam->id_examen, $userId, $classroomId),
                    $teacherId
                );
            }

            return ExamAssistant::forExamAndAssistant($exam->id_examen, $userId)
                ->with(['assistant', 'classroom'])
                ->firstOrFail();
        });
    }

    /** Exámenes vigentes del auxiliar que usa el sistema. */
    public function listForCurrentAssistant(): Collection
    {
        return $this->listForAssistant($this->currentAssistantId());
    }

    /**
     * Exámenes vigentes del auxiliar con su ambiente, del más próximo al más lejano.
     * Los cancelados y finalizados no se muestran: ya no hay ingreso que controlar.
     */
    public function listForAssistant(string $userId): Collection
    {
        return ExamAssistant::query()
            ->select('examen_auxiliar.*')
            ->join('examen', 'examen.id_examen', '=', 'examen_auxiliar.id_examen')
            ->where('examen_auxiliar.id_usuario', $userId)
            ->whereIn('examen.estado', self::ACTIVE_STATES)
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->with(['exam.subject', 'classroom'])
            ->get();
    }

    /**
     * Mientras no haya autenticación, el docente es el de configuración, igual que en
     * ExamService.
     */
    private function currentTeacherId(): string
    {
        $teacherId = $this->subjectCatalog->teacherId();

        if ($teacherId === '') {
            throw new RuntimeException('No hay un docente configurado en SCIEM_DOCENTE_FIJO_ID.');
        }

        return $teacherId;
    }

    /**
     * Mientras no haya autenticación (HU-37), el auxiliar también sale de configuración.
     */
    private function currentAssistantId(): string
    {
        $assistantId = (string) config('sciem.auxiliar_fijo_id');

        if ($assistantId === '') {
            throw new RuntimeException('No hay un auxiliar configurado en SCIEM_AUXILIAR_FIJO_ID.');
        }

        return $assistantId;
    }

    private function assertOwnedBy(Exam $exam, string $teacherId): void
    {
        if ((string) $exam->id_usuario_docente !== $teacherId) {
            throw new ExamOwnershipException();
        }
    }

    private function notEditableMessage(string $status): string
    {
        return $status === Exam::CANCELADO
            ? 'El examen está cancelado: los ambientes de los auxiliares ya no pueden modificarse.'
            : 'El control de ingreso del examen ya se inició: los ambientes de los auxiliares quedaron fijos.';
    }

    private function auditValues(int $examId, string $userId, ?int $classroomId): array
    {
        return [
            'id_examen'   => $examId,
            'id_usuario'  => $userId,
            'id_ambiente' => $classroomId,
        ];
    }
}
