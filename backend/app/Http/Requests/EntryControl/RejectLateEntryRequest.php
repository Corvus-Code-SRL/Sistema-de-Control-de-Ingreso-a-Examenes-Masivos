<?php

namespace App\Http\Requests\EntryControl;

/**
 * Rechazo manual en el ingreso con retraso: el estudiante verificado, su CI, el ambiente de
 * control y el motivo, con las mismas reglas del rechazo del control de ingreso (HU-032).
 */
class RejectLateEntryRequest extends LateEntryManagementRequest
{
    public function rules(): array
    {
        return (new ConfirmEntryRequest())->rules() + RejectEntryRequest::rejectionRules();
    }
}
