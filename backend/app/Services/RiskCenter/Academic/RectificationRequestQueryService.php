<?php

namespace App\Services\RiskCenter\Academic;

use App\Exceptions\RiskCenter\RectificationRequestOwnershipException;
use App\Models\RectificationRequest;
use App\Models\RiskCenter;
use App\Support\Storage\EvidenceStorage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Consultas de las solicitudes de rectificación (HU-062): la bandeja del docente, el conteo de pendientes y el
 * resumen de rectificación que ve el Administrador en cada registro activo.
 *
 * Solo lectura. Una pendiente pasada de fecha se trata como VENCIDA aunque la tarea programada no la haya marcado
 * ({@see RectificationRequest::effectiveState()}) y nunca cuenta como pendiente.
 */
class RectificationRequestQueryService
{
    /** Solicitudes ya respondidas o vencidas que muestra la bandeja, de la más reciente a la más antigua. */
    public const HISTORY_LIMIT = 50;

    private ApprovingTeacherStatusService $teacherStatus;

    private EvidenceStorage $evidence;

    public function __construct(ApprovingTeacherStatusService $teacherStatus, EvidenceStorage $evidence)
    {
        $this->teacherStatus = $teacherStatus;
        $this->evidence = $evidence;
    }

    /**
     * Bandeja del docente: primero las pendientes vigentes (la que vence antes, primero) y después las
     * respondidas y vencidas.
     *
     * @return array{solicitudes: Collection<int, RectificationRequest>, total_pendientes: int}
     */
    public function inbox(string $teacherId): array
    {
        $pending = RectificationRequest::query()
            ->answerable()
            ->where('id_usuario_docente', $teacherId)
            ->orderBy('vence_en')
            ->orderBy('id_solicitud_rect')
            ->get();

        $history = RectificationRequest::query()
            ->where('id_usuario_docente', $teacherId)
            ->where(function ($query) {
                $query->where('estado', '<>', RectificationRequest::PENDING)
                    ->orWhere('vence_en', '<=', Carbon::now());
            })
            ->orderByRaw('COALESCE(fecha_respuesta, vence_en) DESC')
            ->orderByDesc('id_solicitud_rect')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        $all = $pending->concat($history)->values();

        return [
            'solicitudes' => $this->prepareCards(new Collection($all->all())),
            'total_pendientes' => $pending->count(),
        ];
    }

    /** Pendientes vigentes del docente; las vencidas no cuentan. */
    public function countPending(string $teacherId): int
    {
        return RectificationRequest::query()->answerable()->where('id_usuario_docente', $teacherId)->count();
    }

    /**
     * Una solicitud del docente, lista para la tarjeta.
     *
     * @throws ModelNotFoundException
     * @throws RectificationRequestOwnershipException si es de otro docente
     */
    public function findForTeacher(int $id, string $teacherId): RectificationRequest
    {
        $request = RectificationRequest::query()->find($id);

        if ($request === null) {
            throw new ModelNotFoundException('No existe la solicitud de rectificación indicada.');
        }

        if ((string) $request->id_usuario_docente !== $teacherId) {
            throw new RectificationRequestOwnershipException();
        }

        return $this->prepareCards(new Collection([$request]))->first();
    }

    /** Una solicitud sin restricción de dueño (la consulta el Administrador); solo para ubicar la evidencia. */
    public function find(int $id): RectificationRequest
    {
        $request = RectificationRequest::query()->find($id);

        if ($request === null) {
            throw new ModelNotFoundException('No existe la solicitud de rectificación indicada.');
        }

        return $request;
    }

    /**
     * Carga lo que muestra la tarjeta y agrega los atributos `rechazos_previos`, `evidencia_reporte_info` y
     * `evidencia_rechazo_info`, que no se guardan.
     *
     * @param Collection<int, RectificationRequest> $requests
     * @return Collection<int, RectificationRequest>
     */
    private function prepareCards(Collection $requests): Collection
    {
        $requests->load([
            'record.student',
            'record.studentReport.incidentType',
            'record.studentReport.exam.subject',
            'record.studentReport.entryAttempt',
            'requester',
        ]);

        $rejected = RectificationRequest::query()
            ->whereIn('id_cr_estudiante', $requests->pluck('id_cr_estudiante')->unique()->all())
            ->where('estado', RectificationRequest::REJECTED)
            ->get(['id_solicitud_rect', 'id_cr_estudiante', 'fecha_solicitud']);

        foreach ($requests as $request) {
            $previous = $rejected
                ->where('id_cr_estudiante', $request->id_cr_estudiante)
                ->where('fecha_solicitud', '<', $request->fecha_solicitud)
                ->count();

            $request->setAttribute('rechazos_previos', $previous);
            $request->setAttribute(
                'evidencia_reporte_info',
                $this->evidence->describe($request->record->studentReport->evidencia_public_id)
            );
            $request->setAttribute('evidencia_rechazo_info', $this->evidence->describe($request->evidencia_public_id));
        }

        return $requests;
    }

    /**
     * Agrega a cada registro activo el atributo `resumen_rectificacion`, que no se guarda: la solicitud pendiente
     * vigente, cuántas veces se rechazó y venció, el último rechazo y el estado del docente aprobador.
     *
     * Requiere `studentReport.userReviewer` cargado en cada registro.
     *
     * @param Collection<int, RiskCenter> $records
     */
    public function attachSummaries(Collection $records): void
    {
        $history = RectificationRequest::query()
            ->whereIn('id_cr_estudiante', $records->pluck('id_cr_estudiante')->all())
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id_solicitud_rect')
            ->get()
            ->groupBy('id_cr_estudiante');

        foreach ($records as $record) {
            $record->setAttribute('resumen_rectificacion', $this->summary(
                $record,
                $history->get($record->id_cr_estudiante, new Collection())
            ));
        }
    }

    /**
     * @param Collection<int, RectificationRequest> $requests de la más reciente a la más antigua
     * @return array<string, mixed>
     */
    private function summary(RiskCenter $record, Collection $requests): array
    {
        $now = Carbon::now();
        $reviewer = $record->studentReport->userReviewer;
        $pending = $requests->first(fn (RectificationRequest $request) => $request->isAnswerable($now));
        $lastRejection = $requests->first(
            fn (RectificationRequest $request) => $request->estado === RectificationRequest::REJECTED
        );
        $status = $this->teacherStatus->resolve(
            $record->studentReport->id_usuario_revisor,
            (int) $record->id_cr_estudiante
        );

        return [
            'solicitud_pendiente' => $pending === null ? null : [
                'id_solicitud_rect' => (int) $pending->id_solicitud_rect,
                'motivo' => $pending->motivo_solicitud,
                'fecha_solicitud' => $pending->fecha_solicitud->toIso8601String(),
                'vence_en' => $pending->vence_en->toIso8601String(),
                'docente' => ['nombre_completo' => $reviewer?->nombre_completo],
            ],
            'rechazos' => $requests->where('estado', RectificationRequest::REJECTED)->count(),
            'vencidas' => $requests->filter(
                fn (RectificationRequest $request) => $request->effectiveState($now) === RectificationRequest::EXPIRED
            )->count(),
            'ultimo_rechazo' => $lastRejection === null ? null : [
                'id_solicitud_rect' => (int) $lastRejection->id_solicitud_rect,
                'motivo' => $lastRejection->motivo_rechazo,
                'fecha' => $lastRejection->fecha_respuesta->toIso8601String(),
                'evidencia' => $this->evidence->describe($lastRejection->evidencia_public_id),
            ],
            'docente' => [
                'nombre_completo' => $reviewer?->nombre_completo,
                'activo' => $status->active,
                'inactivo_desde' => $status->inactiveSince?->toIso8601String(),
                'fuente' => $status->source,
                'puede_rectificar_desde' => $status->eligibleFrom?->toIso8601String(),
                'puede_rectificar_sin_confirmacion' => $status->canVoidWithoutConfirmation($now),
            ],
            'dias_vencimiento' => (int) config('sciem.rectificacion.dias_vencimiento', 7),
            'dias_docente_inactivo' => (int) config('sciem.rectificacion.dias_docente_inactivo', 180),
        ];
    }
}
