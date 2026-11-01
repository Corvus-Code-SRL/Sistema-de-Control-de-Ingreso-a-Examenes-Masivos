<?php

namespace App\Http\Requests\EntryControl;

/** Autorizar una solicitud: la solicitud viaja en la ruta; solo puede confirmar el otro examen. */
class AuthorizeLateEntryRequest extends LateEntryManagementRequest
{
    public function rules(): array
    {
        return self::otherExamRules();
    }
}
