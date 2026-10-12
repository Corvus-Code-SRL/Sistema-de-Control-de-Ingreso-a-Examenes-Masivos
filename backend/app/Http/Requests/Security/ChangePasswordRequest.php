<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de la contraseña propia. Que la actual coincida lo verifica AuthService con el hash.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contrasenia_actual' => ['required', 'string'],
            // 72 es el límite de bcrypt: más allá, los caracteres no cuentan.
            'contrasenia_nueva'  => [
                'required',
                'string',
                'min:8',
                'max:72',
                'confirmed',
                'different:contrasenia_actual',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'contrasenia_actual' => 'contraseña actual',
            'contrasenia_nueva'  => 'contraseña nueva',
        ];
    }

    public function messages(): array
    {
        return [
            'contrasenia_nueva.min'       => 'La contraseña nueva debe tener al menos 8 caracteres.',
            'contrasenia_nueva.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
            'contrasenia_nueva.different' => 'La contraseña nueva debe ser distinta de la actual.',
        ];
    }
}
