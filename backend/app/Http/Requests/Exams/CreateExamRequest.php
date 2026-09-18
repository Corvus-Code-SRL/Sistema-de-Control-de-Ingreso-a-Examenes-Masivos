<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de validación para la creación de un nuevo examen.
 */
class CreateExamRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación aplicables a la solicitud.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'nombre_examen'  => 'required|string|max:25',
            'id_materia'     => 'required|integer|exists:materia,id_materia',
            'categoria'      => 'sometimes|required|string|in:REGULAR,FINAL,MESA,ADMISION',
            'fecha'          => 'required|date|after_or_equal:today',
            'hora_inicio'    => 'required|date_format:H:i',
            'duracion'       => 'required|integer|min:1',
            'ambientes'      => 'required|array|min:1',
            'ambientes.*'    => 'required|integer|exists:ambiente,id_ambiente',
            //'grupos'         => 'nullable|array',
            //'grupos.*'       => 'integer|exists:grupo,id_grupo',
            'normas'         => 'nullable|string|max:255',
        ];
    }

    /**
     * Mensajes personalizados para los errores de validación.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'nombre_examen.required'  => 'El nombre del examen es obligatorio.',
            'nombre_examen.max'       => 'El nombre del examen no debe exceder los 25 caracteres.',
            'id_materia.required'     => 'La materia es obligatoria.',
            'id_materia.exists'       => 'La materia seleccionada no existe en el sistema.',
            'categoria.in'            => 'La categoría especificada no es válida. Valores permitidos: REGULAR, FINAL, MESA, ADMISION.',
            'fecha.required'          => 'La fecha del examen es obligatoria.',
            'fecha.date'              => 'La fecha del examen no tiene un formato válido.',
            'fecha.after_or_equal'    => 'La fecha del examen no puede ser anterior a la fecha actual.',
            'hora_inicio.required'    => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'duracion.required'       => 'La duración del examen es obligatoria.',
            'duracion.integer'        => 'La duración debe ser un número entero.',
            'duracion.min'            => 'La duración del examen debe ser de al menos 1 minuto.',
            'ambientes.required'      => 'Debe asignar al menos un ambiente para el examen.',
            'ambientes.min'           => 'Debe asignar al menos un ambiente para el examen.',
            'ambientes.*.exists'      => 'Uno o más ambientes seleccionados no son válidos.',
            'grupos.*.exists'         => 'Uno o más grupos seleccionados no existen.',
            'normas.max'              => 'Las normas del examen no deben exceder los 255 caracteres.',
        ];
    }

    /**
     * Nombres personalizados para los atributos de las reglas de validación.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'nombre_examen'  => 'nombre del examen',
            'id_materia'     => 'materia',
            'categoria'      => 'categoría',
            'fecha'          => 'fecha',
            'hora_inicio'    => 'hora de inicio',
            'duracion'       => 'duración',
            'ambientes'      => 'ambientes',
            'ambientes.*'    => 'ambiente',
            'grupos'         => 'grupos',
            'grupos.*'       => 'grupo',
            'normas'         => 'normas',
        ];
    }
}


