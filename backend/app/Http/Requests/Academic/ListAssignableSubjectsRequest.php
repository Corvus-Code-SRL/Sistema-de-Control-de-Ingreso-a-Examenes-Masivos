<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\Academic\Concerns\ValidatesAssignmentPeriod;
use App\Models\SubjectCareer;
use Illuminate\Foundation\Http\FormRequest;

/** Materias asignables a una carrera en un período: el período llega como query param. */
class ListAssignableSubjectsRequest extends FormRequest
{
    use ValidatesAssignmentPeriod;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SubjectCareer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_periodo' => $this->periodRules(),
        ];
    }

    public function attributes(): array
    {
        return [
            'id_periodo' => 'período',
        ];
    }

    public function messages(): array
    {
        return $this->periodMessages();
    }
}
