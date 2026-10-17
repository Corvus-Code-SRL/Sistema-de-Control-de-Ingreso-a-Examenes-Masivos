<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentReportRequest extends FormRequest
{
    public function authorize(UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole($this->user()->id_usuario, [Role::DOCENTE, Role::AUXILIAR]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'examen' => $this->route('examen'),
            'intento' => $this->route('intento'),
            'descripcion' => is_string($this->input('descripcion'))
                ? trim($this->input('descripcion'))
                : $this->input('descripcion'),
        ]);
    }

    public function rules(): array
    {
        return [
            'examen' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'intento' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'id_tipo_falta' => ['bail', 'required', 'integer', 'min:1', 'max:2147483647', 'exists:tipo_falta,id_falta'],
            'descripcion' => ['required', 'string', 'min:10', 'max:500'],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'id_estudiante' => ['prohibited'],
            'id_examen' => ['prohibited'],
            'id_intento' => ['prohibited'],
            'id_usuario_reportante' => ['prohibited'],
            'id_usuario_revisor' => ['prohibited'],
            'estado' => ['prohibited'],
            'evidencia_url' => ['prohibited'],
            'evidencia_public_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_tipo_falta.required' => 'Elija el tipo de falta.',
            'id_tipo_falta.exists' => 'El tipo de falta seleccionado no existe.',
            'descripcion.required' => 'Describa lo ocurrido (mínimo 10 caracteres).',
            'descripcion.min' => 'Describa lo ocurrido (mínimo 10 caracteres).',
            'descripcion.max' => 'La descripción no debe superar los 500 caracteres.',
            'evidencia.file' => 'Formato o tamaño no permitido. Use jpg, png o pdf de hasta 5 MB.',
            'evidencia.mimes' => 'Formato o tamaño no permitido. Use jpg, png o pdf de hasta 5 MB.',
            'evidencia.max' => 'Formato o tamaño no permitido. Use jpg, png o pdf de hasta 5 MB.',
        ];
    }
}
