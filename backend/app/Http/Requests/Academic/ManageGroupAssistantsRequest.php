<?php

namespace App\Http\Requests\Academic;

/**
 * Peticiones de gestión de auxiliares de grupo que no envían datos: «Mis auxiliares», los grupos
 * del docente y quitar un auxiliar de un grupo. Las ejerce quien tiene la función group_assistants.
 */
class ManageGroupAssistantsRequest extends AssistantManagementRequest
{
    protected function feature(): string
    {
        return 'group_assistants';
    }

    public function rules(): array
    {
        return [];
    }
}
