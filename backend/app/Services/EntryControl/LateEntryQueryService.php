<?php

namespace App\Services\EntryControl;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\LateEntryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Solicitudes de ingreso con retraso de un examen (HU-037). El docente ve todas; el
 * auxiliar, solo las que él envió. Las pendientes van primero. Un examen FINALIZADO
 * todavía se consulta, como historial.
 */
class LateEntryQueryService
{
    public const NOT_STARTED_MESSAGE = 'El examen todavía no está en curso.';

    private LateEntryAccessService $access;

    public function __construct(LateEntryAccessService $access)
    {
        $this->access = $access;
    }

    /** @return Collection<int, LateEntryRequest> */
    public function list(Exam $exam, User $actor): Collection
    {
        $access = $this->access->resolve($exam, $actor);

        if (! in_array($exam->estado, [Exam::EN_CURSO, Exam::FINALIZADO], true)) {
            throw new ExamStateException(self::NOT_STARTED_MESSAGE);
        }

        return LateEntryRequest::query()
            ->with(['student', 'classroom', 'requester', 'resolver'])
            ->where('id_examen', $exam->id_examen)
            ->when(
                $access['rol'] === LateEntryAccessService::ASSISTANT,
                fn ($query) => $query->where('id_usuario_solicitante', $actor->id_usuario)
            )
            ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [LateEntryRequest::PENDIENTE])
            ->orderByDesc('solicitado_en')
            ->orderByDesc('id_solicitud')
            ->get();
    }
}
