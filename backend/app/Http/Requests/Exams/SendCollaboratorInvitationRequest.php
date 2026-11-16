<?php

namespace App\Http\Requests\Exams;

class SendCollaboratorInvitationRequest extends CollaboratorInvitationRequest
{
    public function rules(): array
    {
        return [
            'id_docente' => ['required', 'uuid'],
            'permite_agregar_grupos' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_docente.required' => 'Debe indicarse el docente a invitar.',
            'id_docente.uuid' => 'El identificador del docente no es válido.',
            'permite_agregar_grupos.boolean' => 'La opción de agregar grupos no es válida.',
        ];
    }
}
