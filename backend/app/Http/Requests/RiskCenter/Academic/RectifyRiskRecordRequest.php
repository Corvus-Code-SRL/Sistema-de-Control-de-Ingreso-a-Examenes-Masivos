<?php

namespace App\Http\Requests\RiskCenter\Academic;

/** Rectificar un registro es retirarlo de la Central: lo único que se pide es el motivo. */
class RectifyRiskRecordRequest extends RectificationRequest
{
    public const MIN_REASON_LENGTH = 10;

    public const MAX_REASON_LENGTH = 500;

    public function rules(): array
    {
        return [
            'motivo' => ['bail', 'required', 'string', 'min:' . self::MIN_REASON_LENGTH,
                'max:' . self::MAX_REASON_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio',
            'motivo.min' => 'El motivo debe tener al menos ' . self::MIN_REASON_LENGTH . ' caracteres.',
            'motivo.max' => 'El motivo no puede superar los ' . self::MAX_REASON_LENGTH . ' caracteres.',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated()['motivo']);
    }
}
