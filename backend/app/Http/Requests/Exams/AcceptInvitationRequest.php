<?php

namespace App\Http\Requests\Exams;

/**
 * POST /api/invitaciones/{invitation}/aceptar {grupos: [id_grupo, ...]}.
 *
 * `grupos` es opcional y puede ir vacío: aceptar sin sumar grupos. Que los grupos sean del docente, de la
 * materia del examen, activos, del período vigente y con nómina lo valida ExamGroupService (422 en `grupos`).
 */
class AcceptInvitationRequest extends ReceivedInvitationRequest
{
    public function rules(): array
    {
        return [
            'grupos' => ['sometimes', 'array', 'max:50'],
            'grupos.*' => ['integer', 'min:1', 'max:2147483647', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'grupos.array' => 'Los grupos deben enviarse como una lista.',
            'grupos.max' => 'No puede sumar más de 50 grupos a la vez.',
            'grupos.*.integer' => 'Cada grupo debe ser un identificador numérico.',
            'grupos.*.min' => 'Cada grupo debe ser un identificador válido.',
            'grupos.*.max' => 'Cada grupo debe ser un identificador válido.',
            'grupos.*.distinct' => 'Un grupo no puede repetirse.',
        ];
    }

    /** @return array<int, int> */
    public function groupIds(): array
    {
        return array_map('intval', $this->validated()['grupos'] ?? []);
    }
}
