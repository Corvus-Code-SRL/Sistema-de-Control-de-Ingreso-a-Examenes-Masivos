<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de los exámenes de un grupo: solo un docente activo. Que el grupo sea suyo lo decide
 * GroupPolicy en el Controller.
 */
class ShowGroupExamsRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede consultar los exámenes de un grupo.');
    }

    public function rules(): array
    {
        return [
            'id_grupo' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /** El identificador llega como parámetro de ruta, no en el cuerpo de la petición. */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'id_grupo.max' => 'El identificador del grupo no es válido.',
        ];
    }
}
