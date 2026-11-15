<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowExamAttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vista' => ['nullable', Rule::in(['registrados', 'sin_registro'])],
            'q' => ['nullable', 'string', 'max:100'],
            'carrera' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'ambiente' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'estado' => ['nullable', Rule::in([
                'A_TIEMPO', 'CON_RETRASO', 'EXPULSADO', 'SIN_REGISTRO', 'AUSENTE',
                'NO_HABILITADO', 'SIN_CLASIFICAR',
            ])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
