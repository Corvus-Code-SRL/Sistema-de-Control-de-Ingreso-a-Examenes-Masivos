<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\Academic\Concerns\ValidatesAssignmentPeriod;
use App\Models\SubjectCareer;
use Illuminate\Foundation\Http\FormRequest;

/** Ofertas de un período (obligatorio), con filtro opcional por carrera. */
class ListSubjectCareerAssignmentsRequest extends FormRequest
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
            'id_carrera' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_periodo' => 'período',
            'id_carrera' => 'carrera',
        ];
    }

    public function messages(): array
    {
        return array_merge($this->periodMessages(), [
            'id_carrera.max' => 'El identificador de la carrera no es válido.',
        ]);
    }
}
