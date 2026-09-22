<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmStudentRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_grupo' => [
                'required',
                'integer',
                'min:1',
            ],
            'token' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
        ];
    }

    public function validationData(): array
    {
        return array_merge(
            parent::validationData(),
            $this->route()->parameters()
        );
    }

    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'token.required' => 'Debe indicarse el token del preview.',
            'token.string' => 'El token del preview no es válido.',
            'token.size' => 'El token del preview no es válido.',
            'token.regex' => 'El token del preview no es válido.',
        ];
    }
}