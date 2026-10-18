<?php

namespace App\Http\Requests\EntryControl;

use Illuminate\Foundation\Http\FormRequest;

class VerifyStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cod_sis' => ['required_without:ci', 'nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'id_estudiante' => ['prohibited'],
            'ci' => ['required_without:cod_sis', 'nullable', 'string', 'regex:/^[0-9]{1,10}$/'],
            'id_ambiente' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function messages(): array
    {
        return [
            'cod_sis.required_without' => 'Ingrese un código SIS o un CI.',
            'ci.required_without' => 'Ingrese un código SIS o un CI.',
            'cod_sis.regex' => 'El código SIS debe tener exactamente 9 dígitos.',
            'ci.regex' => 'El CI solo puede contener hasta 10 dígitos.',
        ];
    }
}
