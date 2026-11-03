<?php

namespace App\Http\Requests\EntryControl;

/**
 * Verificación por código SIS y CI. El auxiliar envía su ambiente; el docente puede omitirlo
 * porque elige el ambiente al registrar.
 */
class VerifyLateEntryRequest extends LateEntryManagementRequest
{
    public function rules(): array
    {
        $entry = (new VerifyStudentRequest())->rules();

        return [
            'cod_sis' => ['required', 'string', 'regex:/^[0-9]{9}$/'],
            'ci' => $entry['ci'],
            'id_ambiente' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'cod_sis.required' => 'Ingrese el código SIS del estudiante.',
        ];
    }
}
