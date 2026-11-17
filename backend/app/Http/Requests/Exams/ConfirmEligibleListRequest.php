<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Debe indicarse el token del preview.',
            'token.string' => 'El token del preview no es válido.',
            'token.size' => 'El token del preview no es válido.',
            'token.regex' => 'El token del preview no es válido.',
        ];
    }
}
