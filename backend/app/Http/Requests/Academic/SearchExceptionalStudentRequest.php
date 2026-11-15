<?php

namespace App\Http\Requests\Academic;

/**
 * Búsqueda de un estudiante por código SIS dentro de un grupo propio (HU-023).
 */
class SearchExceptionalStudentRequest extends ExceptionalStudentRequest
{
    public function rules(): array
    {
        return [
            'id_grupo' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'cod_sis' => ['required', 'string', 'max:30'],
        ];
    }

    /** El identificador del grupo llega como parámetro de ruta, no en la consulta. */
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
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'id_grupo.max' => 'El identificador del grupo no es válido.',
            'cod_sis.required' => 'Ingrese el código SIS del estudiante.',
            'cod_sis.max' => 'El código SIS es demasiado largo.',
        ];
    }

    public function groupId(): int
    {
        return (int) $this->validated()['id_grupo'];
    }

    public function sisCode(): string
    {
        return (string) $this->validated()['cod_sis'];
    }
}
