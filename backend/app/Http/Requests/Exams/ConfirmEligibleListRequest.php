<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use App\Support\MotivoNoHabilitacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmEligibleListRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para ver esta sección.');
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
            'motivo' => [
                'required',
                'string',
                Rule::in(MotivoNoHabilitacion::all()),
            ],
            'detalle' => [
                'required_if:motivo,' . MotivoNoHabilitacion::OTRO,
                'nullable',
                'string',
                'min:10',
                'max:500',
                'not_regex:/^:/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Debe indicarse el token del preview.',
            'token.string' => 'El token del preview no es válido.',
            'token.size' => 'El token del preview no es válido.',
            'token.regex' => 'El token del preview no es válido.',
            'motivo.required' => 'Debe indicarse el motivo.',
            'motivo.in' => 'El motivo indicado no es válido.',
            'detalle.required_if' => 'El motivo OTRO exige un detalle.',
            'detalle.min' => 'El detalle debe tener al menos 10 caracteres.',
            'detalle.max' => 'El detalle no puede superar los 500 caracteres.',
            'detalle.not_regex' => 'El detalle no puede empezar con ":".',
        ];
    }
}
