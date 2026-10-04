<?php

namespace App\Http\Requests\Academic;

class SearchAssistantRequest extends AssistantManagementRequest
{
    public function rules(): array
    {
        return [
            'criterio' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'criterio.required' => 'Debe indicarse un criterio de búsqueda.',
            'criterio.string' => 'El criterio de búsqueda no es válido.',
            'criterio.min' => 'El criterio debe tener al menos 2 caracteres.',
            'criterio.max' => 'El criterio no puede superar los 100 caracteres.',
        ];
    }
}