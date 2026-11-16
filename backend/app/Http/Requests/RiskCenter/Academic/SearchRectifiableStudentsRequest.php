<?php

namespace App\Http\Requests\RiskCenter\Academic;

use Illuminate\Validation\Validator;

/** Búsqueda de estudiantes por código SIS, carnet de identidad o nombre. */
class SearchRectifiableStudentsRequest extends RectificationRequest
{
    /** Mínimo de caracteres para buscar por nombre; el SIS y el carnet se buscan exactos. */
    public const MIN_NAME_LENGTH = 3;

    public function rules(): array
    {
        return [
            'q' => ['bail', 'required', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Ingrese el código SIS, el carnet de identidad o el nombre del estudiante.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $term = $this->term();

            if (! $validator->errors()->has('q')
                && preg_match('/\p{L}/u', $term) === 1
                && mb_strlen($term) < self::MIN_NAME_LENGTH
            ) {
                $validator->errors()->add(
                    'q',
                    'Para buscar por nombre ingrese al menos ' . self::MIN_NAME_LENGTH . ' caracteres.'
                );
            }
        });
    }

    public function term(): string
    {
        return trim((string) $this->input('q'));
    }
}
