<?php

namespace App\Http\Requests\Academic;

/**
 * Registro de un estudiante excepcional en un examen propio (HU-023).
 */
class StoreExceptionalStudentRequest extends ExceptionalStudentRequest
{
    public const MIN_REASON_LENGTH = 5;

    public const MAX_REASON_LENGTH = 255;

    public function rules(): array
    {
        return [
            'id_examen' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'id_estudiante' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'id_grupo' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'id_carrera' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'id_ambiente' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'motivo' => ['required', 'string', 'min:' . self::MIN_REASON_LENGTH, 'max:' . self::MAX_REASON_LENGTH],
        ];
    }

    /** El identificador del examen llega como parámetro de ruta, no en el cuerpo. */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function attributes(): array
    {
        return [
            'id_estudiante' => 'estudiante',
            'motivo' => 'motivo',
        ];
    }

    public function messages(): array
    {
        return [
            'id_examen.required' => 'Debe indicarse el examen.',
            'id_examen.integer' => 'El identificador del examen debe ser numérico.',
            'id_examen.min' => 'El identificador del examen no es válido.',
            'id_examen.max' => 'El identificador del examen no es válido.',
            'id_estudiante.required' => 'Busque y elija al estudiante antes de registrarlo.',
            'id_estudiante.integer' => 'El estudiante indicado no es válido.',
            'id_estudiante.min' => 'El estudiante indicado no es válido.',
            'id_estudiante.max' => 'El estudiante indicado no es válido.',
            'motivo.required' => 'Indique el motivo del registro excepcional',
            'motivo.string' => 'El motivo debe ser un texto.',
            'motivo.min' => 'El motivo debe tener al menos ' . self::MIN_REASON_LENGTH . ' caracteres.',
            'motivo.max' => 'El motivo no puede superar los ' . self::MAX_REASON_LENGTH . ' caracteres.',
        ];
    }

    public function examId(): int
    {
        return (int) $this->validated()['id_examen'];
    }

    public function studentId(): int
    {
        return (int) $this->validated()['id_estudiante'];
    }

    public function groupId(): int
    {
        return (int) $this->validated()['id_grupo'];
    }

    public function careerId(): int
    {
        return (int) $this->validated()['id_carrera'];
    }

    public function roomId(): int
    {
        return (int) $this->validated()['id_ambiente'];
    }
    public function reason(): string
    {
        return (string) $this->validated()['motivo'];
    }
}
