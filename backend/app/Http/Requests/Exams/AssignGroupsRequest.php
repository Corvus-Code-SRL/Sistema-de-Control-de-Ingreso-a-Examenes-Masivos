<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class AssignGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grupos'   => 'required|array|min:1',
            'grupos.*' => 'required|integer|exists:grupo,id_grupo',
        ];
    }

    public function messages(): array
    {
        return [
            'grupos.required'   => 'Debe seleccionar al menos un grupo para el examen.',
            'grupos.array'      => 'Los grupos deben ser proporcionados en una lista.',
            'grupos.min'        => 'Debe seleccionar al menos un grupo para el examen.',
            'grupos.*.required' => 'El identificador del grupo es obligatorio.',
            'grupos.*.integer'  => 'El identificador del grupo debe ser un número entero.',
            'grupos.*.exists'   => 'Uno o más grupos seleccionados no existen en el sistema.',
        ];
    }

    public function attributes(): array
    {
        return [
            'grupos'   => 'grupos',
            'grupos.*' => 'grupo',
        ];
    }

    /**
     * Validaciones de negocio posteriores a las reglas básicas del FormRequest.
     */
    
}
