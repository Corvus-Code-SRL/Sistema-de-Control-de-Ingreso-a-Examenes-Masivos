<?php

namespace App\Http\Requests\Academic;

use App\Models\SubjectCareer;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectCareerRequest extends FormRequest
{
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
                'exists:materia,id_materia',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_materia' => 'materia',
        ];
    }

    public function messages(): array
    {
        return [
            'id_materia.exists' => 'La materia seleccionada no existe.',
        ];
    }
}