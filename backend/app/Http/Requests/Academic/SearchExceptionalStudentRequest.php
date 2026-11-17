<?php

namespace App\Http\Requests\Academic;

/**
 * Búsqueda de un estudiante por código SIS para un examen propio (HU-023).
 */
class SearchExceptionalStudentRequest extends ExceptionalStudentRequest
{
    public function rules(): array
    {
        return [
            'id_examen' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'cod_sis' => ['required', 'string', 'max:30'],
        ];
    }

    /** El identificador del examen llega como parámetro de ruta, no en la consulta. */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function attributes(): array
    {
        return [
            'cod_sis' => 'código SIS',
        ];
    }

    public function messages(): array
    {
        return [
            'id_examen.required' => 'Debe indicarse el examen.',
            'id_examen.integer' => 'El identificador del examen debe ser numérico.',
            'id_examen.min' => 'El identificador del examen no es válido.',
            'id_examen.max' => 'El identificador del examen no es válido.',
            'cod_sis.required' => 'Ingrese el código SIS del estudiante.',
            'cod_sis.max' => 'El código SIS es demasiado largo.',
        ];
    }

    public function examId(): int
    {
        return (int) $this->validated()['id_examen'];
    }

    public function sisCode(): string
    {
        return (string) $this->validated()['cod_sis'];
    }
}
