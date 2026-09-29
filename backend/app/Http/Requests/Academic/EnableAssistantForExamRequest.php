<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class EnableAssistantForExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_usuario' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_usuario.required' => 'Debe indicarse el auxiliar.',
            'id_usuario.uuid' => 'El identificador del auxiliar no es válido.',
        ];
    }
}