<?php

namespace App\Http\Requests\EntryControl;

use Illuminate\Validation\Rule;

class RejectEntryRequest extends ConfirmEntryRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'motivo' => [
                'required',
                'string',
                Rule::in(['DOCUMENTO_NO_VALIDO', 'IDENTIDAD_DUDOSA', 'DECISION_CONTROLADOR', 'OTRO']),
            ],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
