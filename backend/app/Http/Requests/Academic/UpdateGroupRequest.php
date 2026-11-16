<?php

namespace App\Http\Requests\Academic;

/**
 * Valida los únicos datos editables de un grupo (HU-19, CA 3, 4).
 *
 * id_carrera, id_materia e id_usuario_docente son inmutables: no se declaran, así
 * que validated() nunca los devuelve aunque lleguen en el payload.
 */
class UpdateGroupRequest extends GroupManagementRequest
{
    public function rules(): array
    {
        return [
            'id_grupo' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'num_grupo' => ['required', 'string', 'max:5'],
            'id_periodo' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_ID],
            // HU-021 multicarrera: otras carreras que el grupo atiende, además de la principal.
            'carreras_adicionales' => ['sometimes', 'array', 'max:' . self::MAX_ADDITIONAL_CAREERS],
            'carreras_adicionales.*' => ['integer', 'min:1', 'max:' . self::MAX_ID, 'distinct'],
        ];
    }

    /**
     * El identificador del grupo llega como parámetro de ruta, no en el cuerpo.
     * Se valida aquí para que un valor no numérico responda 422 y no llegue al controlador.
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
            'carreras_adicionales' => 'carreras adicionales',
        ];
    }

    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo a actualizar.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'id_grupo.max' => 'El identificador del grupo no es válido.',
            'num_grupo.required' => 'Debe indicarse el número de grupo.',
            'id_periodo.integer' => 'El identificador del período debe ser numérico.',
            'id_periodo.min' => 'El identificador del período no es válido.',
            'id_periodo.max' => 'El identificador del período no es válido.',
            'carreras_adicionales.array' => 'Las carreras adicionales deben enviarse como lista.',
            'carreras_adicionales.max' => 'Un grupo puede atender como máximo '
                . self::MAX_ADDITIONAL_CAREERS . ' carreras adicionales.',
            'carreras_adicionales.*.integer' => 'El identificador de la carrera debe ser numérico.',
            'carreras_adicionales.*.min' => 'El identificador de la carrera no es válido.',
            'carreras_adicionales.*.max' => 'El identificador de la carrera no es válido.',
            'carreras_adicionales.*.distinct' => 'Una carrera se repite en la lista.',
        ];
    }
}
