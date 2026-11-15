<?php

namespace App\Http\Requests\Academic;

/**
 * Registro de un estudiante excepcional en un grupo propio (HU-023).
 */
class StoreExceptionalStudentRequest extends ExceptionalStudentRequest
{
    public const MAX_REASON_LENGTH = 255;

    public function rules(): array
    {
        return [
            'id_grupo' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'id_estudiante' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'motivo' => ['required', 'string', 'max:' . self::MAX_REASON_LENGTH],
            // Solo hace falta cuando el grupo atiende más de una carrera.
            'id_carrera' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_ID],
        ];
    }

    /** El identificador del grupo llega como parámetro de ruta, no en el cuerpo. */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function attributes(): array
    {
        return [
            'id_estudiante' => 'estudiante',
            'motivo' => 'motivo',
            'id_carrera' => 'carrera',
        ];
    }

    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'id_grupo.max' => 'El identificador del grupo no es válido.',
            'id_estudiante.required' => 'Busque y elija al estudiante antes de registrarlo.',
            'id_estudiante.integer' => 'El estudiante indicado no es válido.',
            'id_estudiante.min' => 'El estudiante indicado no es válido.',
            'id_estudiante.max' => 'El estudiante indicado no es válido.',
            'motivo.required' => 'Indique el motivo del registro excepcional',
            'motivo.string' => 'El motivo debe ser un texto.',
            'motivo.max' => 'El motivo no puede superar los ' . self::MAX_REASON_LENGTH . ' caracteres.',
            'id_carrera.integer' => 'La carrera indicada no es válida.',
        ];
    }

    public function groupId(): int
    {
        return (int) $this->validated()['id_grupo'];
    }

    public function studentId(): int
    {
        return (int) $this->validated()['id_estudiante'];
    }

    public function reason(): string
    {
        return (string) $this->validated()['motivo'];
    }

    public function careerId(): ?int
    {
        $careerId = $this->validated()['id_carrera'] ?? null;

        return $careerId === null ? null : (int) $careerId;
    }
}
