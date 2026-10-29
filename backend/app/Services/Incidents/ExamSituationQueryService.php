<?php

namespace App\Services\Incidents;

use App\Models\Exam;
use App\Models\ExamSituation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Consulta el historial del docente o el historial de origen/destino del aula del auxiliar. */
class ExamSituationQueryService
{
    private ExamSituationAccessService $access;

    public function __construct(ExamSituationAccessService $access)
    {
        $this->access = $access;
    }

    public function paginate(int $examId, array $filters, User $actor): LengthAwarePaginator
    {
        $exam = Exam::query()->findOrFail($examId);
        $roomScope = $this->access->roomScope($exam, $actor);
        $roomId = isset($filters['id_ambiente']) ? (int) $filters['id_ambiente'] : $roomScope;

        if ($roomId !== null) {
            $this->access->assertRoomAllowed($exam, $roomId, $roomScope);
        }

        $query = ExamSituation::query()
            ->with(['student', 'originRoom', 'destinationRoom', 'reporter'])
            ->where('id_examen', $examId);

        if ($roomId !== null) {
            $query->where(function (Builder $rooms) use ($roomId): void {
                $rooms->where('id_ambiente_origen', $roomId)->orWhere('id_ambiente_destino', $roomId);
            });
        }

        return $query->orderByDesc('registrado_en')
            ->orderByDesc('id_situacion')
            ->paginate(20, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }
}