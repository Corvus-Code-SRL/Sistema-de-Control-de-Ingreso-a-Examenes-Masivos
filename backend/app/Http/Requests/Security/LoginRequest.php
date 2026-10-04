<?php

namespace App\Http\Requests\Security;

use App\Support\SisCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inicio de sesión con código SIS y contraseña.
 *
 * El SIS no se valida por formato ni longitud: conviven 5 dígitos (docentes), 9 (auxiliares y
 * estudiantes) y alfanuméricos (Administrador). Solo se normaliza, igual que al guardarlo.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->cod_sis)) {
            $this->merge(['cod_sis' => SisCode::normalize($this->cod_sis)]);
        }
    }

    public function rules(): array
    {
        return [
            'cod_sis' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cod_sis' => 'código SIS',
            'password' => 'contraseña',
        ];
    }
}
