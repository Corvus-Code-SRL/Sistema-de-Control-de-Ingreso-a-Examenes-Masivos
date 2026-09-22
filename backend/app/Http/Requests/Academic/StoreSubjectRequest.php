<?php

namespace App\Http\Requests\Academic;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Subject::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre'      => ['required', 'string', 'max:50'],
            'codigo'      => [
                'required',
                'string',
                'max:50',
                'regex:' . Subject::CODIGO_REGEX,
                'unique:materia,codigo',
            ],
            'descripcion' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre'      => 'nombre de la materia',
            'codigo'      => 'código de la materia',
            'descripcion' => 'descripción',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ya existe una materia registrada con el código :input.',
            'codigo.regex'  => 'El código debe contener exactamente 7 dígitos (ej: 2008057).',
        ];
    }
}