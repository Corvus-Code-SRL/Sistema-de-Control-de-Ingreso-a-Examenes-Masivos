<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'num_grupo' => ['required', 'string', 'max:5'],
            'id_periodo' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'num_grupo' => 'número de grupo',
            'id_periodo' => 'período académico',
        ];
    }

    public function messages(): array
    {
        return [
            'num_grupo.required' => 'Debe indicarse el número de grupo.',
        ];
    }
}