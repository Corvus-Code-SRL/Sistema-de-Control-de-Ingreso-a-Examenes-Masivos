<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // Sprint 1: sin autenticación real
    }

    public function rules(): array
    {
        return [
            // CA 4 y 6 — rol obligatorio y que exista en el catálogo
            'id_rol' => ['required', 'integer', 'exists:rol,id_rol'],
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
            'id_rol.exists'   => 'El rol seleccionado no existe en el sistema.',
        ];
    }
}