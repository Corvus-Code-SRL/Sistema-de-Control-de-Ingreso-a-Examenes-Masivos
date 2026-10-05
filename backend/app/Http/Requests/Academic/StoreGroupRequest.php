<?php

namespace App\Http\Requests\Academic;

/**
 * Valida forma y obligatoriedad de los datos del grupo (CA 2, 4).
 *
 * La verificación de duplicidad sobre la quíntupla y la validación de que el
 * par materia-carrera exista y esté activo (CA 3, 7) se resuelven en
 * GroupService: dependen de datos que este FormRequest no tiene cargados.
 */
class StoreGroupRequest extends GroupManagementRequest
{
    public function rules(): array
    {
        return [
            'id_carrera' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'id_materia' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'num_grupo' => ['required', 'string', 'max:5'],
            'id_periodo' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_ID],
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
            'id_carrera.integer' => 'El identificador de la carrera debe ser numérico.',
            'id_carrera.min' => 'El identificador de la carrera no es válido.',
            'id_carrera.max' => 'El identificador de la carrera no es válido.',
            'id_materia.required' => 'Debe indicarse la materia.',
            'id_materia.integer' => 'El identificador de la materia debe ser numérico.',
            'id_materia.min' => 'El identificador de la materia no es válido.',
            'id_materia.max' => 'El identificador de la materia no es válido.',
            'num_grupo.required' => 'Debe indicarse el número de grupo.',
            'id_periodo.integer' => 'El identificador del período debe ser numérico.',
            'id_periodo.min' => 'El identificador del período no es válido.',
            'id_periodo.max' => 'El identificador del período no es válido.',
        ];
    }
}
