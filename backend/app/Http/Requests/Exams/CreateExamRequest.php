<?php

namespace App\Http\Requests\Exams;

use Illuminate\Validation\Rule;

/**
 * Creación de un examen con los grupos que rendirán la evaluación.
 */
class CreateExamRequest extends ExamRequest
{
    private const MAX_ID = 2147483647;

    public function rules(): array
    {
        return array_merge(parent::rules(), [
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
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'grupos.required' => 'Debe seleccionar al menos un grupo para el examen.',
            'grupos.array' => 'Los grupos deben enviarse como una lista.',
            'grupos.min' => 'Debe seleccionar al menos un grupo para el examen.',
            'grupos.*.distinct' => 'Un mismo grupo no puede seleccionarse dos veces.',
            'grupos.*.exists' => 'Uno o más grupos seleccionados no existen.',
            'grupos.*.*' => 'Uno o más grupos seleccionados no son válidos.',
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'grupos' => 'grupos',
            'grupos.*' => 'grupo',
        ]);
    }
}
