<?php

namespace App\Http\Requests\Exams;

/** GET /api/invitaciones/{invitation} y POST /api/invitaciones/{invitation}/rechazar: sin cuerpo que validar. */
class ShowReceivedInvitationRequest extends ReceivedInvitationRequest
{
    public function rules(): array
    {
        return [];
    }
}
