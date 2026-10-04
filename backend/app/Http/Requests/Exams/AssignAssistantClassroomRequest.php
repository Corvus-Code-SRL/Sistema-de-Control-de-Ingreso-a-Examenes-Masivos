<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ambiente que el docente asigna a un auxiliar habilitado (HU-09).
 *
 * Aquí solo se valida el formato. Que el ambiente sea uno de los del examen y que el
 * examen siga programado lo decide AssistantClassroomService.
 */
class AssignAssistantClassroomRequest extends FormRequest
{
    private const MAX_ID = 2147483647;

    /**
     * Quien asigna es el docente: una cuenta con rol Auxiliar no puede, aunque esté habilitada
     * en el examen. Sin sesión se actúa como el docente fijo, igual que en el resto de Exams.
     */
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return ! $roles->isAssistant($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Un auxiliar no puede asignar ambientes a otros auxiliares.');
    }

    public function rules(): array
    {
        return [
            'id_ambiente' => ['bail', 'required', 'integer', 'min:1', 'max:' . self::MAX_ID],
        ];
    }

    public function messages(): array
    {
        return [
            'id_ambiente.required' => 'Debe seleccionar un ambiente.',
            'id_ambiente.integer' => 'El ambiente seleccionado no es válido.',
            'id_ambiente.min' => 'El ambiente seleccionado no es válido.',
            'id_ambiente.max' => 'El ambiente seleccionado no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_ambiente' => 'ambiente',
        ];
    }
}
