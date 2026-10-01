<?php

namespace App\Http\Requests\EntryControl;

use Illuminate\Foundation\Http\FormRequest;

class VerifyStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cod_sis' => ['required', 'string', 'max:15'],
            'id_estudiante' => ['prohibited'],
            'ci' => ['nullable', 'string', 'min:1', 'max:10'],
            'id_ambiente' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
