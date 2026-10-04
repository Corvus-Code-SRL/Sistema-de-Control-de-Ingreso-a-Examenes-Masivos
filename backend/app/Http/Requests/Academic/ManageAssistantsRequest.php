<?php

namespace App\Http\Requests\Academic;

/**
 * Peticiones de gestión de auxiliares que no envían datos: listados y quitar de grupo o examen.
 */
class ManageAssistantsRequest extends AssistantManagementRequest
{
    public function rules(): array
    {
        return [];
    }
}
