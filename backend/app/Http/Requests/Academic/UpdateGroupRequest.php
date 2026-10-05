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
        ];
    }
}
