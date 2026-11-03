<?php

namespace App\Services\Incidents;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Consultas del acceso «Reportar incidencia» (HU-042). Reutiliza la consulta de HU-057 con sus
 * propios estados (solo En curso), su propia función de acceso y sin estudiantes retirados.
 */
class InProgressIncidentWorkspaceService
{
    public const MESSAGE_NOT_IN_PROGRESS = 'Solo se pueden reportar incidencias con el examen en curso.';

    private ExamSituationWorkspaceService $workspace;

    public function __construct(ExamSituationWorkspaceService $workspace)
    {
        $this->workspace = $workspace;
    }

    public function exams(array $filters, User $actor): LengthAwarePaginator
    {
        return $this->workspace->exams(['page' => $filters['page'] ?? 1], $actor, $this->scope());
    }

    public function context(int $examId, array $filters, User $actor): array
    {
        $context = $this->workspace->context($examId, $filters, $actor, $this->scope());
        $ids = $context['page']->getCollection()->pluck('id_estudiante')->all();

        $context['incident_counts'] = $ids === [] ? [] : DB::table('reporte_estudiante')
            ->where('id_examen', $examId)->where('origen', 'EN_CURSO')->whereIn('id_estudiante', $ids)
            ->selectRaw('id_estudiante, count(*) as total')->groupBy('id_estudiante')
            ->pluck('total', 'id_estudiante')->all();

        return $context;
    }

    private function scope(): array
    {
        return [
            'states' => [Exam::EN_CURSO],
            'feature' => 'incidents',
            'exclude_expelled' => true,
            'with_summaries' => false,
            'state_message' => self::MESSAGE_NOT_IN_PROGRESS,
        ];
    }
}
