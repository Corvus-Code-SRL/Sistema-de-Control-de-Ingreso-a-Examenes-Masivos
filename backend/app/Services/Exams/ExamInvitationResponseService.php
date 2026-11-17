<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Group;
use App\Models\Period;
use App\Services\Security\AuditLogService;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Invitaciones recibidas por un docente y su respuesta (HU-027).
 *
 * El docente invitado ve las invitaciones dirigidas a él y las acepta o rechaza mientras están PENDIENTES y el
 * examen sigue PROGRAMADO sin haber abierto el control de ingreso (examen.ingreso_abierto_en nulo, HU-038).
 * Al aceptar puede sumar sus propios grupos de la materia, si la invitación lo permite, y desde ese momento es
 * docente del examen (ver ExamCollaboratorService). Enviar invitaciones es de HU-026
 * (CollaboratorInvitationService).
 *
 * Pestañas de la bandeja, según el estado que se muestra (ExamInvitation::effectiveState()):
 * - pendientes: PENDIENTE con el examen PROGRAMADO y el control de ingreso sin abrir.
 * - aceptadas: ACEPTADA.
 * - cerradas: RECHAZADA, SIN_EFECTO y la PENDIENTE cuyo examen ya abrió el control o salió de PROGRAMADO.
 */
class ExamInvitationResponseService
{
    public const TABS = ['pendientes', 'aceptadas', 'cerradas'];

    public const ALREADY_ANSWERED_MESSAGE = 'La invitación ya fue respondida o quedó sin efecto.';

    public const EXAM_NOT_SCHEDULED_MESSAGE =
        'La invitación ya no se puede responder: el examen ya abrió el control de ingreso o no está programado.';

    public const GROUPS_NOT_ALLOWED_MESSAGE = 'Esta invitación no permite agregar grupos al examen.';

    public const NOT_FOUND_MESSAGE = 'No existe la invitación indicada.';

    private ExamGroupService $examGroups;

    private AuditLogService $auditLog;

    public function __construct(ExamGroupService $examGroups, AuditLogService $auditLog)
    {
        $this->examGroups = $examGroups;
        $this->auditLog = $auditLog;
    }

    /**
     * Invitaciones recibidas en una pestaña, de la más reciente a la más antigua, con el total de cada pestaña.
     *
     * @return array{invitaciones: Collection<int, ExamInvitation>, totales: array<string, int>}
     */
    public function listReceived(string $teacherId, string $tab): array
    {
        $this->assertTeacherGiven($teacherId);

        $invitations = $this->receivedQuery($teacherId)
            ->where(fn (Builder $query) => $this->whereTab($query, $tab))
            ->with(['exam.subject', 'exam.career', 'userCreator'])
            ->orderByDesc('invitacion_examen.fecha_invitacion')
            ->orderByDesc('invitacion_examen.id_invitacion')
            ->get();

        $totals = [];
        foreach (self::TABS as $name) {
            $totals[$name] = $this->receivedQuery($teacherId)
                ->where(fn (Builder $query) => $this->whereTab($query, $name))
                ->count();
        }

        return ['invitaciones' => $invitations, 'totales' => $totals];
    }

    /** Detalle de una invitación recibida; 404 si no existe o es de otro docente. */
    public function show(int $invitationId, string $teacherId): ExamInvitation
    {
        $this->assertTeacherGiven($teacherId);

        return $this->withDetail($this->findReceived($invitationId, $teacherId), $teacherId);
    }

    /**
     * Acepta la invitación y suma los grupos elegidos.
     *
     * Bloquea primero la fila del examen (como la apertura del control de ingreso y el envío de invitaciones) y
     * luego la de la invitación: así no se cruza con la apertura del control ni con otra respuesta simultánea.
     *
     * @param array<int, int> $groupIds
     */
    public function accept(int $invitationId, string $teacherId, array $groupIds): ExamInvitation
    {
        $this->assertTeacherGiven($teacherId);

        $invitation = DB::transaction(function () use ($invitationId, $teacherId, $groupIds) {
            [$invitation, $exam] = $this->lockForResponse($invitationId, $teacherId);

            $groupIds = array_values(array_unique(array_map('intval', $groupIds)));

            if ($groupIds !== [] && ! $invitation->permite_agregar_grupos) {
                throw ValidationException::withMessages(['grupos' => [self::GROUPS_NOT_ALLOWED_MESSAGE]]);
            }

            $attached = $this->examGroups->attachCollaboratorGroups($exam, $groupIds, $teacherId);

            $answeredAt = Carbon::now();
            $invitation->forceFill([
                'estado' => ExamInvitation::ACEPTADA,
                'fecha_respuesta' => $answeredAt,
            ])->save();

            $this->auditLog->registrar(
                'ACEPTAR_INVITACION',
                'invitacion_examen',
                ['id_invitacion' => $invitation->id_invitacion, 'estado' => ExamInvitation::PENDIENTE],
                [
                    'id_invitacion' => $invitation->id_invitacion,
                    'id_examen' => $exam->id_examen,
                    'estado' => ExamInvitation::ACEPTADA,
                    'fecha_respuesta' => $answeredAt->toIso8601String(),
                    'grupos_sumados' => $attached,
                ],
                $teacherId
            );

            return $invitation;
        });

        return $this->withDetail($invitation->fresh(), $teacherId);
    }

    /** Rechaza la invitación. No guarda motivo: la tabla no tiene esa columna. */
    public function reject(int $invitationId, string $teacherId): ExamInvitation
    {
        $this->assertTeacherGiven($teacherId);

        $invitation = DB::transaction(function () use ($invitationId, $teacherId) {
            [$invitation, $exam] = $this->lockForResponse($invitationId, $teacherId);

            $answeredAt = Carbon::now();
            $invitation->forceFill([
                'estado' => ExamInvitation::RECHAZADA,
                'fecha_respuesta' => $answeredAt,
            ])->save();

            $this->auditLog->registrar(
                'RECHAZAR_INVITACION',
                'invitacion_examen',
                ['id_invitacion' => $invitation->id_invitacion, 'estado' => ExamInvitation::PENDIENTE],
                [
                    'id_invitacion' => $invitation->id_invitacion,
                    'id_examen' => $exam->id_examen,
                    'estado' => ExamInvitation::RECHAZADA,
                    'fecha_respuesta' => $answeredAt->toIso8601String(),
                ],
                $teacherId
            );

            return $invitation;
        });

        return $this->withDetail($invitation->fresh(), $teacherId);
    }

    /**
     * Bloquea examen e invitación y comprueba que todavía se pueda responder.
     *
     * @return array{0: ExamInvitation, 1: Exam}
     */
    private function lockForResponse(int $invitationId, string $teacherId): array
    {
        $examId = $this->findReceived($invitationId, $teacherId)->id_examen;

        $exam = Exam::query()->whereKey($examId)->lockForUpdate()->firstOrFail();
        $invitation = ExamInvitation::query()->whereKey($invitationId)->lockForUpdate()->firstOrFail();

        if ($invitation->estado !== ExamInvitation::PENDIENTE) {
            throw new ExamStateException(self::ALREADY_ANSWERED_MESSAGE);
        }

        if ($exam->estado !== Exam::PROGRAMADO || $exam->ingreso_abierto_en !== null) {
            throw new ExamStateException(self::EXAM_NOT_SCHEDULED_MESSAGE);
        }

        $invitation->setRelation('exam', $exam);

        return [$invitation, $exam];
    }

    private function findReceived(int $invitationId, string $teacherId): ExamInvitation
    {
        $invitation = ExamInvitation::query()
            ->whereKey($invitationId)
            ->where('id_docente_invitado', $teacherId)
            ->first();

        if ($invitation === null) {
            throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE);
        }

        return $invitation;
    }

    private function receivedQuery(string $teacherId): Builder
    {
        return ExamInvitation::query()
            ->select('invitacion_examen.*')
            ->join('examen', 'examen.id_examen', '=', 'invitacion_examen.id_examen')
            ->where('invitacion_examen.id_docente_invitado', $teacherId);
    }

    private function whereTab(Builder $query, string $tab): void
    {
        if ($tab === 'aceptadas') {
            $query->where('invitacion_examen.estado', ExamInvitation::ACEPTADA);

            return;
        }

        if ($tab === 'pendientes') {
            $query->where('invitacion_examen.estado', ExamInvitation::PENDIENTE)
                ->where('examen.estado', Exam::PROGRAMADO)
                ->whereNull('examen.ingreso_abierto_en');

            return;
        }

        $query->whereIn('invitacion_examen.estado', [ExamInvitation::RECHAZADA, ExamInvitation::SIN_EFECTO])
            ->orWhere(function (Builder $stale) {
                $stale->where('invitacion_examen.estado', ExamInvitation::PENDIENTE)
                    ->where(fn (Builder $exam) => $exam->where('examen.estado', '<>', Exam::PROGRAMADO)
                        ->orWhereNotNull('examen.ingreso_abierto_en'));
            });
    }

    /** Carga lo que muestra el detalle: examen, ambientes, grupos del examen y grupos propios del invitado. */
    private function withDetail(ExamInvitation $invitation, string $teacherId): ExamInvitation
    {
        $invitation->load([
            'exam.subject',
            'exam.career',
            'exam.classrooms',
            'exam.groups' => fn ($query) => $query->withStudentCount()->orderBy('grupo.num_grupo'),
            'userCreator',
        ]);

        $exam = $invitation->exam;
        $examGroupIds = $exam->groups->pluck('id_grupo')->map(fn ($id) => (int) $id)->all();

        $invitation->setAttribute('periodo', $this->periodLabel($exam));
        $invitation->setAttribute('mis_grupos', $this->ownGroups($exam, $teacherId, $examGroupIds));

        return $invitation;
    }

    /**
     * Grupos del invitado en la materia del examen, activos y del período vigente.
     *
     * @param array<int, int> $examGroupIds
     * @return array<int, array<string, mixed>>
     */
    private function ownGroups(Exam $exam, string $teacherId, array $examGroupIds): array
    {
        $periodId = Period::query()->current()->value('id_gestion_periodo');

        if ($periodId === null) {
            return [];
        }

        return Group::query()
            ->withStudentCount()
            ->where('grupo.id_usuario_docente', $teacherId)
            ->where('grupo.id_materia', $exam->id_materia)
            ->where('grupo.id_gestion_periodo', $periodId)
            ->where('grupo.estado', RecordStatus::ACTIVE)
            ->orderBy('grupo.num_grupo')
            ->get()
            ->map(function (Group $group) use ($examGroupIds) {
                $enrolled = (int) $group->cantidad_estudiantes;
                $added = in_array((int) $group->id_grupo, $examGroupIds, true);

                return [
                    'id_grupo' => (int) $group->id_grupo,
                    'num_grupo' => (string) $group->num_grupo,
                    'inscritos' => $enrolled,
                    'seleccionable' => ! $added && $enrolled > 0,
                    'motivo' => $added || $enrolled > 0 ? null : 'sin nómina',
                    'sumado' => $added,
                ];
            })
            ->values()
            ->all();
    }

    /** Período de los grupos del examen («2/2026»); null si el examen aún no tiene grupos. */
    private function periodLabel(Exam $exam): ?string
    {
        $periodId = $exam->groups->pluck('id_gestion_periodo')->filter()->first();

        if ($periodId === null) {
            return null;
        }

        return Period::query()->withLabel()->find((int) $periodId)?->label;
    }

    private function assertTeacherGiven(string $teacherId): void
    {
        if ($teacherId === '') {
            throw new RuntimeException('No hay un docente autenticado que ejecute la operación.');
        }
    }
}
