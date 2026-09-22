<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida forma y obligatoriedad de los datos del grupo (CA 2, 4).
 *
 * La verificación de duplicidad sobre la quíntupla y la validación de que el
 * par materia-carrera exista y esté activo (CA 3, 7) se resuelven en
 * GroupService: dependen de datos que este FormRequest no tiene cargados.
 */
class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sprint 1: sin autenticación real
    }

    public function rules(): array
    {
        return [
            'id_carrera' => ['required', 'integer', 'min:1'],
            'id_materia' => ['required', 'integer', 'min:1'],
            'num_grupo' => ['required', 'string', 'max:5'],
            'id_periodo' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_carrera' => 'carrera',
            'id_materia' => 'materia',
            'num_grupo' => 'número de grupo',
            'id_periodo' => 'período académico',
        ];
    }

    public function messages(): array
    {
        return [
            'id_carrera.required' => 'Debe indicarse la carrera.',
            'id_materia.required' => 'Debe indicarse la materia.',
            'num_grupo.required' => 'Debe indicarse el número de grupo.',
        ];
    }
}