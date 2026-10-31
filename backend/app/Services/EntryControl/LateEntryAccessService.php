<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\UserRoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Quién controla un examen en el ingreso con retraso (HU-037): su docente propietario o un
 * auxiliar habilitado en su ambiente. Es la misma regla que la vista En curso (HU-038-A).
 */
class LateEntryAccessService
{
    public const TEACHER = 'DOCENTE';
    public const ASSISTANT = 'AUXILIAR';

    public const NOT_ENABLED_MESSAGE = 'No está habilitado para este examen.';
    public const TEACHER_ONLY_MESSAGE = 'Solo el docente del examen puede realizar esta acción.';
    public const ASSISTANT_ONLY_MESSAGE = 'Solo un auxiliar del examen puede solicitar un ingreso con retraso.';

    private UserRoleService $roles;

    public function __construct(UserRoleService $roles)
    {
        $this->roles = $roles;
    }

    /**
     * @return array{rol: string, id_ambiente: int|null} el ambiente de control del auxiliar;
     *     null para el docente, que controla todos
     */
    public function resolve(Exam $exam, User $actor): array
    {
        if ($actor->estado !== User::ESTADO_ACTIVO) {
            throw new AuthorizationException(EntryControlSnapshotService::CONTROL_DENIED_MESSAGE);
        }

        if ((string) $exam->id_usuario_docente === (string) $actor->id_usuario) {
            if (! $this->roles->isTeacher($actor->id_usuario)) {
                throw new AuthorizationException(EntryControlSnapshotService::CONTROL_DENIED_MESSAGE);
            }

            return ['rol' => self::TEACHER, 'id_ambiente' => null];
        }

        if (! $this->roles->hasAnyActiveRole($actor->id_usuario, [Role::AUXILIAR])) {
            throw new AuthorizationException(EntryControlSnapshotService::CONTROL_DENIED_MESSAGE);
        }

        $assignment = ExamAssistant::query()
            ->forExamAndAssistant((int) $exam->id_examen, (string) $actor->id_usuario)
            ->first(['id_ambiente']);

        if ($assignment === null) {
            throw new AuthorizationException(self::NOT_ENABLED_MESSAGE);
        }

        $roomId = EntryControlSnapshotService::controlRoomOf(
            $assignment->id_ambiente === null ? null : (int) $assignment->id_ambiente,
            $this->rooms($exam)
        );

        if ($roomId === null) {
            throw new AuthorizationException(EntryControlSnapshotService::UNASSIGNED_ROOM_MESSAGE);
        }

        return ['rol' => self::ASSISTANT, 'id_ambiente' => $roomId];
    }

    /** @param array{rol: string, id_ambiente: int|null} $access */
    public function assertTeacher(array $access): void
    {
        if ($access['rol'] !== self::TEACHER) {
            throw new AuthorizationException(self::TEACHER_ONLY_MESSAGE);
        }
    }

    /** @param array{rol: string, id_ambiente: int|null} $access */
    public function assertAssistant(array $access): void
    {
        if ($access['rol'] !== self::ASSISTANT) {
            throw new AuthorizationException(self::ASSISTANT_ONLY_MESSAGE);
        }
    }

    /**
     * Ambiente con el que se verifica. El auxiliar solo actúa en el suyo y la verificación
     * exige que el estudiante corresponda a él. El docente puede indicar cualquier ambiente
     * del examen, pero la verificación no lo compara: lo elige él (null).
     *
     * @param array{rol: string, id_ambiente: int|null} $access
     */
    public function controlRoom(Exam $exam, array $access, ?int $requestedRoomId): ?int
    {
        if ($access['rol'] === self::ASSISTANT) {
            if ($requestedRoomId !== $access['id_ambiente']) {
                throw new AuthorizationException(EntryControlSnapshotService::FOREIGN_ROOM_MESSAGE);
            }

            return $access['id_ambiente'];
        }

        $this->assertRoomInExam($exam, $requestedRoomId);

        return null;
    }

    /** El ambiente donde el docente registra debe ser uno del examen. */
    public function assertRoomInExam(Exam $exam, ?int $roomId): void
    {
        if ($roomId !== null && ! in_array($roomId, array_column($this->rooms($exam), 'id_ambiente'), true)) {
            throw new AuthorizationException(EntryControlSnapshotService::ROOM_NOT_IN_EXAM_MESSAGE);
        }
    }

    /** @return array<int, array{id_ambiente: int}> */
    private function rooms(Exam $exam): array
    {
        return DB::table('examen_ambiente')
            ->where('id_examen', $exam->id_examen)
            ->orderBy('id_ambiente')
            ->pluck('id_ambiente')
            ->map(fn ($roomId) => ['id_ambiente' => (int) $roomId])
            ->all();
    }
}
