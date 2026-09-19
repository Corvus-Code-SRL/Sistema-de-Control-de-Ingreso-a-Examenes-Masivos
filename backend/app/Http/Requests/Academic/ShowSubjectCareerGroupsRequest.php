<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class ShowSubjectCareerGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_carrera' => ['required', 'integer', 'min:1'],
            'id_materia' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Los identificadores llegan como parámetros de ruta, no en el cuerpo de la petición.
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function messages(): array
    {
        return [
            'id_carrera.required' => 'Debe indicarse la carrera.',
            'id_carrera.integer' => 'El identificador de la carrera debe ser numérico.',
            'id_carrera.min' => 'El identificador de la carrera no es válido.',
            'id_materia.required' => 'Debe indicarse la materia.',
            'id_materia.integer' => 'El identificador de la materia debe ser numérico.',
            'id_materia.min' => 'El identificador de la materia no es válido.',
        ];
    }
}
