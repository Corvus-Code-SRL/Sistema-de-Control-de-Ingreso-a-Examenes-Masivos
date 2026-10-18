<?php

namespace App\Http\Requests\EntryControl;

class ConfirmEntryRequest extends VerifyStudentRequest
{
    public function rules(): array
    {
        return [
            'id_estudiante' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'cod_sis' => ['prohibited'],
            'ci' => ['nullable', 'string', 'regex:/^[0-9]{1,10}$/'],
            'id_ambiente' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
