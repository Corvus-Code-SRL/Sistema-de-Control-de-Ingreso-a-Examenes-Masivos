<?php

namespace App\Http\Requests\RiskCenter\Academic;

use Illuminate\Http\UploadedFile;

/** El docente rechaza una solicitud de rectificación con un motivo y, si quiere, una imagen de evidencia. */
class RejectRectificationRequestRequest extends RespondRectificationRequest
{
    public const MIN_REASON_LENGTH = 10;

    public const MAX_REASON_LENGTH = 500;

    public const MAX_IMAGE_KB = 5120;

    private const IMAGE_MESSAGE = 'Formato o tamaño no permitido. Use una imagen jpg, png o webp de hasta 5 MB.';

    public function rules(): array
    {
        return [
            'motivo' => ['bail', 'required', 'string', 'min:' . self::MIN_REASON_LENGTH,
                'max:' . self::MAX_REASON_LENGTH],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_IMAGE_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio',
            'motivo.min' => 'El motivo debe tener al menos ' . self::MIN_REASON_LENGTH . ' caracteres.',
            'motivo.max' => 'El motivo no puede superar los ' . self::MAX_REASON_LENGTH . ' caracteres.',
            'evidencia.file' => self::IMAGE_MESSAGE,
            'evidencia.mimes' => self::IMAGE_MESSAGE,
            'evidencia.max' => self::IMAGE_MESSAGE,
            'evidencia.uploaded' => self::IMAGE_MESSAGE,
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated()['motivo']);
    }

    public function image(): ?UploadedFile
    {
        $file = $this->file('evidencia');

        return $file instanceof UploadedFile ? $file : null;
    }
}
