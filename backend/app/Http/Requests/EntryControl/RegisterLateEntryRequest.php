<?php

namespace App\Http\Requests\EntryControl;

/** Registro directo del docente: el estudiante verificado, su CI y el ambiente que elige. */
class RegisterLateEntryRequest extends LateEntryManagementRequest
{
    public function rules(): array
    {
        return (new ConfirmEntryRequest())->rules();
    }
}
