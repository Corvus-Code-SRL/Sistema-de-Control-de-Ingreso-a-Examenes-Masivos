<?php

namespace App\Http\Requests\Security;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use App\Support\RecordStatus;
use App\Support\SisCode;
use App\Support\SisCodeFormat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro de una cuenta (HU-001).
 *
 * Con `sin_codigo_sis` la cuenta es de un docente invitado: no lleva código SIS (el sistema genera
 * el código INV). Sin él, el código es obligatorio y su formato depende del rol elegido.
 *
 * Un invitado puede traer en `cod_invitado` el código INV que se le mostró al administrador (ver
 * GET /usuarios/codigo-invitado); si ya no está libre, el registro responde 409 con otro.
 */
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

    /** El SIS se normaliza antes de validar para comparar siempre la forma canónica. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->cod_sis)) {
            $this->merge(['cod_sis' => SisCode::normalize($this->cod_sis)]);
        }

        if (is_string($this->cod_invitado)) {
            $this->merge(['cod_invitado' => SisCode::normalize($this->cod_invitado)]);
        }
    }

    public function rules(): array
    {
        $isGuest = $this->boolean('sin_codigo_sis');

        return [
            'sin_codigo_sis'   => ['sometimes', 'boolean'],
            'cod_sis'          => $isGuest
                ? ['prohibited']
                : ['required', 'string', 'max:15'],
            'cod_invitado'     => $isGuest
                ? ['nullable', 'string', 'regex:/^' . SisCode::GUEST_PREFIX . '[1-9][0-9]{3}$/']
                : ['prohibited'],
            'id_rol'           => [
                'nullable',
                'integer',
                Rule::exists('rol', 'id_rol')->where('estado', RecordStatus::ACTIVE),
            ],
            'nombre'           => ['required', 'string', 'max:50'],
            // El SIS devuelve los apellidos en un solo campo: usuario admite hasta 60 caracteres.
            'apellido_paterno' => ['required', 'string', 'max:60'],
            'apellido_materno' => ['nullable', 'string', 'max:60'],
            'correo'           => ['required', 'email', 'max:100', 'unique:usuario,correo'],
            // 72 es el límite de bcrypt: más allá, los caracteres no cuentan.
            'contrasenia_temporal' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $code = $this->input('cod_sis');

            $hasErrors = $validator->errors()->hasAny(['cod_sis', 'id_rol']);

            if ($this->boolean('sin_codigo_sis') || ! is_string($code) || $hasErrors) {
                return;
            }

            $roleName = $this->filled('id_rol')
                ? DB::table('rol')->where('id_rol', $this->input('id_rol'))->value('nombre_rol')
                : null;

            if (! SisCodeFormat::isValid($code, $roleName)) {
                // Un código bien formado que no corresponde al rol es un problema de la combinación: se
                // informa en `id_rol` para que el formulario no mande a corregir un código que está bien.
                $field = SisCodeFormat::isValid($code, null) ? 'id_rol' : 'cod_sis';
                $validator->errors()->add($field, $this->formatMessage($code, $roleName));

                return;
            }

            // El código se guarda en mayúsculas, pero una fila antigua podría estar en minúsculas.
            if (DB::table('usuario')->whereRaw('upper(cod_sis) = ?', [$code])->exists()) {
                $validator->errors()->add('cod_sis', 'Ya existe una cuenta registrada con este código SIS.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'cod_sis'          => 'código SIS',
            'cod_invitado'     => 'código de invitado',
            'id_rol'           => 'rol',
            'nombre'           => 'nombres',
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
            'correo'           => 'correo institucional',
            'contrasenia_temporal' => 'contraseña temporal',
        ];
    }

    public function messages(): array
    {
        return [
            'cod_sis.prohibited' => 'Un docente invitado no lleva código SIS: el sistema genera su código de acceso.',
            'cod_sis.required' => 'Ingrese el código SIS de la cuenta.',
            'cod_invitado.regex' => 'El código de un docente invitado tiene el formato INV1234.',
            'cod_invitado.prohibited' => 'Solo un docente invitado lleva código INV.',
            // CA 4 — usuario duplicado
            'correo.unique'  => 'Ya existe una cuenta registrada con este correo institucional.',
            'correo.email'   => 'El correo institucional no tiene un formato válido.',
            'contrasenia_temporal.required' => 'Ingrese la contraseña temporal de la cuenta.',
            'contrasenia_temporal.min'      => 'La contraseña temporal debe tener al menos 8 caracteres.',
        ];
    }

    private function formatMessage(string $code, ?string $roleName): string
    {
        if (SisCode::isGuest($code)) {
            return 'Los códigos que empiezan con INV son de docentes invitados y los genera el sistema.';
        }

        switch ($roleName) {
            case Role::AUXILIAR:
                return 'El código SIS de un Auxiliar tiene 9 dígitos y empieza con un año entre 1990 y el actual (por ejemplo 202312345).';
            case Role::ADMINISTRADOR:
                return 'El código SIS de un Administrador son letras seguidas de dígitos (por ejemplo ADM0001).';
            case Role::DOCENTE:
                return 'El código SIS de un Docente es alfanumérico, de 3 a 15 caracteres (por ejemplo 10452).';
            default:
                return 'El código SIS no tiene un formato válido: use 9 dígitos que empiecen con un año, letras seguidas de dígitos, o 3 a 15 caracteres alfanuméricos.';
        }
    }
}
