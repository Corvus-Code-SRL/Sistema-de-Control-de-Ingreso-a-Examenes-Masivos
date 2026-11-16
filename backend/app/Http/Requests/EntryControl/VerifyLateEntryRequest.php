<?php

namespace App\Http\Requests\EntryControl;

/**
 * Verificación por código SIS y CI desde un ambiente de control: el del auxiliar o el que elige
 * el docente. Es obligatorio porque un resultado negativo deja el intento en ese ambiente.
 */
class VerifyLateEntryRequest extends LateEntryManagementRequest
{
    public const CONTROL_ROOM_REQUIRED_MESSAGE = 'Seleccione el ambiente de control.';

    public function rules(): array
    {
        $entry = (new VerifyStudentRequest())->rules();

        return [
            'cod_sis' => ['required', 'string', 'regex:/^[0-9]{9}$/'],
            'ci' => $entry['ci'],
            'id_ambiente' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'cod_sis.required' => 'Ingrese el código SIS del estudiante.',
            'id_ambiente.required' => self::CONTROL_ROOM_REQUIRED_MESSAGE,
        ];
    }
}
