<?php

namespace App\Http\Requests\Academic;

class EnableAssistantForExamRequest extends AssistantManagementRequest
{
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