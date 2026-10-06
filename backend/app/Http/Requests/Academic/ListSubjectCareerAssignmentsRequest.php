<?php

namespace App\Http\Requests\Academic;

use App\Models\SubjectCareer;
use Illuminate\Foundation\Http\FormRequest;

/** Listado de pares materia-carrera, con filtro opcional por carrera. */
class ListSubjectCareerAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SubjectCareer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_carrera' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_carrera' => 'carrera',
        ];
    }

    public function messages(): array
    {
        return [
            'id_carrera.max' => 'El identificador de la carrera no es válido.',
        ];
    }
}
