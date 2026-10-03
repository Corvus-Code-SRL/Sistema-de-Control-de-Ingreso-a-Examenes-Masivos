<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ambiente que el docente asigna a un auxiliar habilitado (HU-09).
 *
 * Aquí solo se valida el formato. Que el ambiente sea uno de los del examen y que el
 * examen siga programado lo decide AssistantClassroomService.
 */
class AssignAssistantClassroomRequest extends FormRequest
{
    private const MAX_ID = 2147483647;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_ambiente' => ['bail', 'required', 'integer', 'min:1', 'max:' . self::MAX_ID],
        ];
    }

    public function messages(): array
    {
        return [
            'id_ambiente.required' => 'Debe seleccionar un ambiente.',
            'id_ambiente.integer' => 'El ambiente seleccionado no es válido.',
            'id_ambiente.min' => 'El ambiente seleccionado no es válido.',
            'id_ambiente.max' => 'El ambiente seleccionado no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_ambiente' => 'ambiente',
        ];
    }
}
