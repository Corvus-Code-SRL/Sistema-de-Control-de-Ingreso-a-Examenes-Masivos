<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\Academic\Concerns\ValidatesAssignmentPeriod;
use App\Models\SubjectCareer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HU-008: desvincular una materia de una carrera en el período vigente.
 * El período se valida como en HU-006 (existe y está ACTIVO); que sea el
 * vigente se decide en el Service, que devuelve el mensaje exacto.
 */
class UnassignSubjectCareerRequest extends FormRequest
{
    use ValidatesAssignmentPeriod;

    public function authorize(): bool
    {
        return $this->user()?->can('create', SubjectCareer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_carrera' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
                Rule::exists('carrera', 'id_carrera'),
            ],
            'id_periodo' => $this->periodRules(),
            'id_materia' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
                Rule::exists('materia', 'id_materia'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_carrera' => 'carrera',
            'id_periodo' => 'período',
            'id_materia' => 'materia',
        ];
    }

    public function messages(): array
    {
        return array_merge($this->periodMessages(), [
            'id_carrera.max' => 'El identificador de la carrera no es válido.',
            'id_carrera.exists' => 'La carrera seleccionada no existe.',
            'id_materia.max' => 'El identificador de la materia no es válido.',
            'id_materia.exists' => 'La materia seleccionada no existe.',
        ]);
    }
}
