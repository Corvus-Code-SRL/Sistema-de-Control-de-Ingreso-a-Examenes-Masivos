<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class AddAssistantToGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => ['integer', 'min:1'],
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
        ];
    }
}