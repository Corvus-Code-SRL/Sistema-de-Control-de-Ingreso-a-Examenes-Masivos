<?php

namespace App\Http\Requests\EntryControl;

/** Solicitud del auxiliar: el estudiante verificado, su CI y el ambiente del auxiliar. */
class StoreLateEntryRequestRequest extends LateEntryManagementRequest
{
    public function rules(): array
    {
        return (new ConfirmEntryRequest())->rules();
    }
}
