<?php

namespace App\Http\Requests\Security;

use App\Support\RecordStatus;
use Illuminate\Validation\Rule;

/**
 * Asignación o modificación del rol de una cuenta.
 *
 * Solo se aceptan roles del catálogo de SCIEM que sigan activos.
 */
class AssignRoleRequest extends AdministratorRequest
{
    public function rules(): array
    {
        return [
            'id_rol' => [
                'required',
                'integer',
                Rule::exists('rol', 'id_rol')->where('estado', RecordStatus::ACTIVE),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['id_rol' => 'rol'];
    }

    public function messages(): array
    {
        return [
            'id_rol.required' => 'Debe seleccionar un rol.',
            'id_rol.integer'  => 'El rol seleccionado no es válido.',
            'id_rol.exists'   => 'El rol seleccionado no existe en el sistema.',
        ];
    }
}
