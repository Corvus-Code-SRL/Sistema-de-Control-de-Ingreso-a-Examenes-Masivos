<?php

namespace App\Http\Requests\Academic;

class ConfirmStudentRosterRequest extends RosterManagementRequest
{
    public function rules(): array
    {
        return [
            'id_grupo' => $this->groupIdRules(),
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
        return $this->groupIdMessages() + [
            'token.required' => 'Debe indicarse el token del preview.',
            'token.string' => 'El token del preview no es válido.',
            'token.size' => 'El token del preview no es válido.',
            'token.regex' => 'El token del preview no es válido.',
        ];
    }
}
