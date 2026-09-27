<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nro_aula' => ['required', 'string', 'max:50', 'unique:ambiente,nro_aula'],
            'capacidad' => ['required', 'integer', 'min:1'],
            'ubicacion' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nro_aula.unique' => 'Ya existe un ambiente registrado con ese nombre.',
            'capacidad.min' => 'La capacidad debe ser mayor a cero.',
        ];
    }
}