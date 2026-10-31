<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\Academic\Concerns\ValidatesAssignmentPeriod;
use App\Models\SubjectCareer;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectCareerRequest extends FormRequest
{
    use ValidatesAssignmentPeriod;

    public function authorize(): bool
    {
        return $this->user()?->can('create', SubjectCareer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_materia' => [
                'required',
                'integer',
                'max:2147483647',
                'exists:materia,id_materia',
            ],
            'id_periodo' => $this->periodRules(),
        ];
    }

    public function attributes(): array
    {
        return [
            'id_materia' => 'materia',
            'id_periodo' => 'período',
        ];
    }

    public function messages(): array
    {
        return array_merge($this->periodMessages(), [
            'id_materia.max' => 'El identificador de la materia no es válido.',
            'id_materia.exists' => 'La materia seleccionada no existe.',
        ]);
    }
}
