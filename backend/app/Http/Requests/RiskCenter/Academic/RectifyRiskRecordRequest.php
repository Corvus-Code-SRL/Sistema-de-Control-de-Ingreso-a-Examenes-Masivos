<?php

namespace App\Http\Requests\RiskCenter\Academic;

/** Nueva versión de un registro: solo el tipo de falta y la descripción se pueden corregir. */
class RectifyRiskRecordRequest extends RectificationRequest
{
    public const MIN_REASON_LENGTH = 10;

    public const MAX_REASON_LENGTH = 500;

    public const MAX_DESCRIPTION_LENGTH = 2000;

    public function rules(): array
    {
        return [
            'id_tipo_falta' => ['bail', 'required', 'integer', 'min:1', 'max:' . self::MAX_ID,
                'exists:tipo_falta,id_falta'],
            'descripcion' => ['nullable', 'string', 'max:' . self::MAX_DESCRIPTION_LENGTH],
            'motivo' => ['bail', 'required', 'string', 'min:' . self::MIN_REASON_LENGTH,
                'max:' . self::MAX_REASON_LENGTH],
            'version_base' => ['bail', 'required', 'integer', 'min:1', 'max:' . self::MAX_ID],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio',
            'motivo.min' => 'El motivo debe tener al menos ' . self::MIN_REASON_LENGTH . ' caracteres.',
            'motivo.max' => 'El motivo no puede superar los ' . self::MAX_REASON_LENGTH . ' caracteres.',
            'id_tipo_falta.exists' => 'El tipo de falta indicado no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_tipo_falta' => 'tipo de falta',
            'descripcion' => 'descripción',
            'version_base' => 'versión base',
        ];
    }

    public function faultTypeId(): int
    {
        return (int) $this->validated()['id_tipo_falta'];
    }

    public function description(): ?string
    {
        $description = trim((string) ($this->validated()['descripcion'] ?? ''));

        return $description === '' ? null : $description;
    }

    public function reason(): string
    {
        return trim((string) $this->validated()['motivo']);
    }

    public function baseVersion(): int
    {
        return (int) $this->validated()['version_base'];
    }
}
