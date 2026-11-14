<?php

namespace App\Http\Requests\EntryControl;

use Illuminate\Validation\Rule;

class RejectEntryRequest extends ConfirmEntryRequest
{
    /** Motivos del rechazo manual; los comparte el ingreso con retraso (HU-037). */
    public const REASONS = ['DOCUMENTO_NO_VALIDO', 'IDENTIDAD_DUDOSA', 'DECISION_CONTROLADOR', 'OTRO'];

    public function rules(): array
    {
        return array_merge(parent::rules(), self::rejectionRules());
    }

    /** @return array<string, array<int, string|\Illuminate\Validation\Rules\In>> */
    public static function rejectionRules(): array
    {
        return [
            'motivo' => ['required', 'string', Rule::in(self::REASONS)],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
