<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class ShowGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_grupo' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * El identificador llega como parámetro de ruta, no en el cuerpo de la petición.
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
        ];
    }
}
