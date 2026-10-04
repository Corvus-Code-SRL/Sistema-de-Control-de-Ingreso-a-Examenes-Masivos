<?php

namespace App\Http\Requests\Academic;

class AddAssistantToGroupsRequest extends AssistantManagementRequest
{
    private const MAX_ID = 2147483647;

    public function rules(): array
    {
        return [
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => ['integer', 'min:1', 'max:' . self::MAX_ID, 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'grupos.required' => 'Debe seleccionar al menos un grupo.',
            'grupos.array' => 'Los grupos deben enviarse como una lista.',
            'grupos.min' => 'Debe seleccionar al menos un grupo.',
            'grupos.*.integer' => 'Cada grupo debe ser un identificador numérico.',
            'grupos.*.min' => 'El identificador del grupo no es válido.',
            'grupos.*.max' => 'El identificador del grupo no es válido.',
            'grupos.*.distinct' => 'No se puede repetir un grupo en la selección.',
        ];
    }
}