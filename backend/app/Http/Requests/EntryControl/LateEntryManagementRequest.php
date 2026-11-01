<?php

namespace App\Http\Requests\EntryControl;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones del ingreso con retraso (HU-037).
 *
 * Opera un docente o un auxiliar con la cuenta activa; cualquier otra cuenta recibe 403. Si
 * controla este examen, en qué ambiente y qué acción le corresponde lo decide
 * LateEntryAccessService.
 */
abstract class LateEntryManagementRequest extends FormRequest
{
    public const FORBIDDEN_MESSAGE = 'Solo un docente o un auxiliar puede registrar ingresos con retraso.';
    public const CONFIRM_OTHER_EXAM_MESSAGE = 'La confirmación del otro examen debe ser verdadera o falsa.';

    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        // El Invitado ejerce la docencia (sciem.access.teaching): no se compara el rol por nombre.
        return $roles->hasAnyActiveRole(
            $currentUser->id(),
            array_merge($roles->rolesFor('teaching'), [Role::AUXILIAR])
        );
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(self::FORBIDDEN_MESSAGE);
    }

    public function rules(): array
    {
        return [];
    }

    /** Los mismos textos de validación del control de ingreso (HU-032). */
    public function messages(): array
    {
        return (new VerifyStudentRequest())->messages() + [
            'confirmar_otro_examen.boolean' => self::CONFIRM_OTHER_EXAM_MESSAGE,
        ];
    }

    /** Continuar pese a que el estudiante ya ingresó a otro examen abierto. */
    protected static function otherExamRules(): array
    {
        return ['confirmar_otro_examen' => ['sometimes', 'boolean']];
    }
}
