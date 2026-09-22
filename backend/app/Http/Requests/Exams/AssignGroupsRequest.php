<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Selección de grupos que rendirán un examen programado.
 */
class AssignGroupsRequest extends FormRequest
{
    private const MAX_ID = 2147483647;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => [
                'bail',
                'required',
                'integer',
                'min:1',
                'max:' . self::MAX_ID,
                'distinct',
                Rule::exists('grupo', 'id_grupo'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'grupos.required' => 'Debe seleccionar al menos un grupo para el examen.',
            'grupos.array' => 'Los grupos deben enviarse como una lista.',
            'grupos.min' => 'Debe seleccionar al menos un grupo para el examen.',
            'grupos.*.distinct' => 'Un mismo grupo no puede seleccionarse dos veces.',
            'grupos.*.exists' => 'Uno o más grupos seleccionados no existen.',
            'grupos.*.*' => 'Uno o más grupos seleccionados no son válidos.',
        ];
    }

    public function attributes(): array
    {
        return [
            'grupos' => 'grupos',
            'grupos.*' => 'grupo',
        ];
    }
}
