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

    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($currentUser->id(), [Role::DOCENTE, Role::AUXILIAR]);
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
        return (new VerifyStudentRequest())->messages();
    }
}
