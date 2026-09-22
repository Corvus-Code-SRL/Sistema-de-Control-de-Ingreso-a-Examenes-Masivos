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
            'id_grupo' => ['required', 'integer', 'min:1'],
            'num_grupo' => ['required', 'string', 'max:5'],
            'id_periodo' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * El identificador del grupo llega como parámetro de ruta, no en el body.
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
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
            'id_grupo.required' => 'Debe indicarse el grupo a actualizar.',
            'num_grupo.required' => 'Debe indicarse el número de grupo.',
        ];
    }
}