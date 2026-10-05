<?php

namespace App\Http\Requests\Security;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use App\Support\SisCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isAdministrator($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un Administrador puede registrar cuentas.');
    }

    /** El SIS se normaliza antes de validar para que 'unique' compare la forma canónica. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->cod_sis)) {
            $this->merge(['cod_sis' => SisCode::normalize($this->cod_sis)]);
        }
    }

    public function rules(): array
    {
        return [
            // CA 1, 7, 8 — campos obligatorios y formato
            'cod_sis'          => ['required', 'string', 'max:15', 'unique:usuario,cod_sis'],
            'nombre'           => ['required', 'string', 'max:50'],
            'apellido_paterno' => ['required', 'string', 'max:30'],
            'apellido_materno' => ['nullable', 'string', 'max:30'],
            'correo'           => ['required', 'email', 'max:100', 'unique:usuario,correo'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cod_sis'          => 'código SIS',
            'nombre'           => 'nombres',
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
            'correo'           => 'correo institucional',
        ];
    }

    public function messages(): array
    {
        return [
            // CA 4 — usuario duplicado
            'cod_sis.unique' => 'Ya existe una cuenta registrada con este código SIS.',
            'correo.unique'  => 'Ya existe una cuenta registrada con este correo institucional.',
            'correo.email'   => 'El correo institucional no tiene un formato válido.',
        ];
    }
}
