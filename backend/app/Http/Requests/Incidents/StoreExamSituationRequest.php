<?php

namespace App\Http\Requests\Incidents;

use App\Models\Role;
use App\Services\Security\UserRoleService;
use App\Support\ExamSituationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valida los datos de una situación sin aceptar identidad ni estado impuestos por el cliente. */
class StoreExamSituationRequest extends FormRequest
{
    public function authorize(UserRoleService $roles): bool
    {
        return $roles->hasAnyActiveRole(
            $this->user()?->id_usuario,
            array_merge($roles->rolesFor('teaching'), [Role::AUXILIAR])
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'examen' => $this->route('examen'),
            'estudiante' => $this->route('estudiante'),
            'motivo' => is_string($this->input('motivo'))
                ? trim($this->input('motivo'))
                : $this->input('motivo'),
        ]);
    }

    public function rules(): array
    {
        $isRoomChange = $this->input('tipo') === ExamSituationType::ROOM_CHANGE;

        return [
            'examen' => ['required', 'integer', 'min:1', 'max:999999999'],
            'estudiante' => ['required', 'integer', 'min:1', 'max:999999999'],
            'tipo' => ['required', 'string', Rule::in(ExamSituationType::values())],
            'motivo' => ['bail', 'required', 'string', $isRoomChange ? 'min:1' : 'min:10', 'max:500'],
            'id_ambiente_destino' => $isRoomChange
                ? ['required', 'integer', 'min:1', 'max:999999999']
                : ['prohibited'],
            'id_examen' => ['prohibited'],
            'id_estudiante' => ['prohibited'],
            'id_ambiente_origen' => ['prohibited'],
            'id_usuario_registrante' => ['prohibited'],
            'id_usuario_reportante' => ['prohibited'],
            'registrado_en' => ['prohibited'],
            'estado' => ['prohibited'],
            'estado_ingreso' => ['prohibited'],
            'id_reporte_est' => ['prohibited'],
            'id_intento' => ['prohibited'],
            'id_tipo_falta' => ['prohibited'],
            'evidencia' => ['prohibited'],
            'evidencia_url' => ['prohibited'],
            'evidencia_public_id' => ['prohibited'],
            'retirar_estudiante' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        $reasonMessage = $this->input('tipo') === ExamSituationType::ROOM_CHANGE
            ? 'Indique el motivo del cambio.'
            : 'Describa lo ocurrido (mínimo 10 caracteres).';

        return [
            'tipo.required' => 'Seleccione un tipo de situación.',
            'tipo.in' => 'Seleccione un tipo de situación válido.',
            'motivo.required' => $reasonMessage,
            'motivo.min' => $reasonMessage,
            'motivo.string' => 'El motivo debe ser un texto.',
            'motivo.max' => 'El texto no debe superar los 500 caracteres.',
            'id_ambiente_destino.required' => 'Seleccione otro ambiente de este examen.',
            'id_ambiente_destino.prohibited' => 'Una observación de identidad no cambia el ambiente.',
        ];
    }
}