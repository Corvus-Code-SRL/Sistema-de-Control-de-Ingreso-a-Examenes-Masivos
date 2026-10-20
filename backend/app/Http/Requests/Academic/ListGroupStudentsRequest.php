<?php

namespace App\Http\Requests\Academic;

/**
 * Estudiantes de un grupo (HU-020): solo un docente activo (GroupManagementRequest); que el grupo
 * sea suyo lo decide GroupPolicy en el Controller.
 */
class ListGroupStudentsRequest extends GroupManagementRequest
{
    public const MAX_SEARCH_LENGTH = 50;

    // Un OFFSET desbordado haría fallar la consulta: se acota la página que se puede pedir.
    private const MAX_PAGE = 100000;

    public function rules(): array
    {
        return [
            'id_grupo' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'q' => ['nullable', 'string', 'max:' . self::MAX_SEARCH_LENGTH],
            'page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PAGE],
        ];
    }

    /** El identificador llega como parámetro de ruta, no en la consulta. */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function attributes(): array
    {
        return [
            'q' => 'búsqueda',
            'page' => 'página',
        ];
    }

    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'id_grupo.max' => 'El identificador del grupo no es válido.',
        ];
    }

    public function groupId(): int
    {
        return (int) $this->validated()['id_grupo'];
    }

    public function search(): ?string
    {
        return $this->validated()['q'] ?? null;
    }

    public function page(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }
}
