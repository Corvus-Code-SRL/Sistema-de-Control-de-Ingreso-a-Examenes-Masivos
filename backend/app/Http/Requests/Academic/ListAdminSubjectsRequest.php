<?php

namespace App\Http\Requests\Academic;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;

/** Consulta del catálogo administrativo de materias, con búsqueda opcional por código o nombre. */
class ListAdminSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Subject::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'búsqueda',
        ];
    }
}
