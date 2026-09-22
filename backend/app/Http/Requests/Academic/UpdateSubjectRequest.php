<?php

namespace App\Http\Requests\Academic;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            && ($this->user()?->can('update', $subject) ?? false);
    }

    public function rules(): array
    {
        /** @var Subject $subject */
        $subject = $this->route('subject');

        return [
            'nombre' => ['required', 'string', 'max:50'],
            'codigo' => [
                'required',
                'string',
                'max:50',
                'regex:' . Subject::CODIGO_REGEX,
                Rule::unique('materia', 'codigo')
                    ->ignore($subject->id_materia, 'id_materia'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la materia',
            'codigo' => 'código de la materia',
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