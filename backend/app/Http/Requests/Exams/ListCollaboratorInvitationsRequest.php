<?php

namespace App\Http\Requests\Exams;

/**
 * Peticiones de lectura de la vista de invitaciones: exámenes invitables e invitaciones enviadas.
 */
class ListCollaboratorInvitationsRequest extends CollaboratorInvitationRequest
{
    public function rules(): array
    {
        return [];
    }
}
