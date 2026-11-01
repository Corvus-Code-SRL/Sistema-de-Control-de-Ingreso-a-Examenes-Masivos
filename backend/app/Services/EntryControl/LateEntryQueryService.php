<?php

namespace App\Services\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\LateEntryRequest;
use App\Models\User;
use App\Support\Database\AccentInsensitiveSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Solicitudes de ingreso con retraso de un examen (HU-037). El docente ve todas; el
 * auxiliar, solo las que él envió. Las pendientes van primero. Un examen FINALIZADO
 * todavía se consulta, como historial. Las pendientes llevan la advertencia de otro examen,
 * calculada en una sola consulta para toda la lista.
 */
class LateEntryQueryService
{
    public const NOT_STARTED_MESSAGE = 'El examen todavía no está en curso.';
    public const SEARCH_LIMIT = 5;

    /** Nombre completo como lo arma el control de ingreso; es un literal, nunca texto del usuario. */
    private const FULL_NAME = "concat_ws(' ', est.nombre, est.apellido_paterno, est.apellido_materno)";

    private LateEntryAccessService $access;
    private LateEntryOtherExamService $otherExams;

    public function __construct(LateEntryAccessService $access, LateEntryOtherExamService $otherExams)
    {
        $this->access = $access;
        $this->otherExams = $otherExams;
    }

    /**
     * @return array{
     *     solicitudes: Collection<int, LateEntryRequest>,
     *     otros_examenes: array<int, array{id_examen: int, nombre_examen: string, materia: string|null,
     *         docente: string|null, hora_ingreso: string|null, nro_aula: string|null}>
     * }
     */
    public function list(Exam $exam, User $actor): array
    {
        $access = $this->access->resolve($exam, $actor);

        if (! in_array($exam->estado, [Exam::EN_CURSO, Exam::FINALIZADO], true)) {
            throw new ExamStateException(self::NOT_STARTED_MESSAGE);
        }

        $requests = LateEntryRequest::query()
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
        $pendingStudentIds = $requests
            ->where('estado', LateEntryRequest::PENDIENTE)
            ->map(fn (LateEntryRequest $request) => (int) $request->id_estudiante)
            ->values()
            ->all();

        return [
            'solicitudes' => $requests,
            'otros_examenes' => $this->otherExams->forStudents($exam, $pendingStudentIds),
        ];
    }

    /**
     * Sugerencias del ingreso con retraso: lo mismo que la búsqueda de HU-032, pero sobre la
     * nómina en PostgreSQL, porque durante EN_CURSO ya no hay snapshot en Redis. El CI solo
     * vuelve en la búsqueda por CI.
     *
     * @param array{nombre?: string, sis?: string, ci?: string} $input
     * @return array<int, array{id_estudiante: int, cod_sis: string, nombre_completo: string, ci?: string|null}>
     */
    public function search(Exam $exam, User $actor, array $input): array
    {
        $this->access->resolve($exam, $actor);

        if ($exam->estado !== Exam::EN_CURSO) {
            throw LateEntryException::examNotInProgress();
        }

        $query = DB::table('estudiante as est')
            ->whereExists(fn (Builder $roster) => $roster
                ->from('grupo_estudiante as gs')
                ->join('grupo_examen as gx', 'gx.id_grupo', '=', 'gs.id_grupo')
                ->whereColumn('gs.id_estudiante', 'est.id_estudiante')
                ->where('gx.id_examen', $exam->id_examen))
            ->select(['est.id_estudiante', 'est.cod_sis', 'est.ci'])
            ->selectRaw(self::FULL_NAME . ' as nombre_completo')
            ->limit(self::SEARCH_LIMIT);

        if (isset($input['nombre'])) {
            $query->whereRaw(
                AccentInsensitiveSearch::condition(self::FULL_NAME),
                [AccentInsensitiveSearch::pattern(AccentInsensitiveSearch::normalize($input['nombre']))]
            )->orderByRaw(self::FULL_NAME);
        } else {
            $column = isset($input['sis']) ? 'est.cod_sis' : 'est.ci';
            $query->where($column, 'like', ($input['sis'] ?? $input['ci']) . '%')->orderBy($column);
        }

        $withCi = isset($input['ci']);

        return $query->get()->map(fn (object $student): array => [
            'id_estudiante' => (int) $student->id_estudiante,
            'cod_sis' => $student->cod_sis,
            'nombre_completo' => $student->nombre_completo,
        ] + ($withCi ? ['ci' => $student->ci] : []))->all();
    }
}
