<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isAdministrator($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un Administrador puede registrar ambientes.');
    }

    /** Limpia nro_aula antes de validar, para que el valor guardado no arrastre espacios. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->nro_aula)) {
            $this->merge([
                'nro_aula' => preg_replace('/\s+/', ' ', trim($this->nro_aula)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'nro_aula' => [
                'required',
                'string',
                'max:10',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Compara sin distinguir mayúsculas ni espacios, contra filas activas e
                    // inactivas por igual: la constraint UNIQUE(nro_aula) de la base hace lo mismo.
                    $exists = DB::table('ambiente')
                        ->whereRaw('lower(trim(nro_aula)) = lower(?)', [$value])
                        ->exists();

                    if ($exists) {
                        $fail('Ya existe un ambiente registrado con ese nombre.');
                    }
                },
            ],
            'capacidad' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'ubicacion' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            // ambiente.nro_aula es varchar(10) y ambiente.capacidad es integer (int4).
            'nro_aula.max' => 'El nombre del ambiente no puede superar los 10 caracteres.',
            'capacidad.min' => 'La capacidad debe ser mayor a cero.',
            'capacidad.max' => 'La capacidad no puede superar 2147483647.',
        ];
    }
}