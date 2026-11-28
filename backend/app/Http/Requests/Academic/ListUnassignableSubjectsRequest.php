<?php

namespace App\Http\Requests\Academic;

use App\Models\SubjectCareer;
use App\Support\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HU-008: lista de materias de una carrera en un período (con conteo de grupos).
 * `periodo` es opcional: si falta, el Service usa el vigente.
 */
class ListUnassignableSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SubjectCareer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'carrera' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
                Rule::exists('carrera', 'id_carrera'),
            ],
            'periodo' => [
                'nullable',
                'integer',
                'min:1',
                'max:2147483647',
                Rule::exists('gestion_periodo', 'id_gestion_periodo')
                    ->where('estado', RecordStatus::ACTIVE),
            ],
            'buscar' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'carrera' => 'carrera',
            'periodo' => 'período',
            'buscar' => 'búsqueda',
            'page' => 'página',
            'per_page' => 'tamaño de página',
        ];
    }
}
