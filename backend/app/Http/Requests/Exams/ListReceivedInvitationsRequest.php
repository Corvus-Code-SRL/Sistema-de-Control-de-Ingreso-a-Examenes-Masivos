<?php

namespace App\Http\Requests\Exams;

use App\Services\Exams\ExamInvitationResponseService;
use Illuminate\Validation\Rule;

/** GET /api/invitaciones?estado=pendientes|aceptadas|cerradas (por defecto, pendientes). */
class ListReceivedInvitationsRequest extends ReceivedInvitationRequest
{
    public function rules(): array
    {
        return [
            'estado' => ['sometimes', 'string', Rule::in(ExamInvitationResponseService::TABS)],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in' => 'El filtro de estado debe ser pendientes, aceptadas o cerradas.',
        ];
    }

    public function tab(): string
    {
        return $this->validated()['estado'] ?? 'pendientes';
    }
}
