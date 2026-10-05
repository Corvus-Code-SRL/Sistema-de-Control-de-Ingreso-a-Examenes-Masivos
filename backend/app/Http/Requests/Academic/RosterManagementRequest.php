<?php

namespace App\Http\Requests\Academic;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Base de las peticiones de carga de nómina (HU-21).
 *
 * Cargar la nómina es gestionar el grupo: una cuenta con rol Auxiliar no puede, con la misma
 * regla que GroupManagementRequest. Que el grupo sea del docente lo decide GroupPolicy
 * (manageRoster) y que esté activo, sea del período vigente y no esté congelado por un examen,
 * StudentRosterGroupAccess.
 */
abstract class RosterManagementRequest extends GroupManagementRequest
{
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Un auxiliar no puede cargar la nómina de un grupo.');
    }

    /** @return array<int, string> */
    protected function groupIdRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:' . self::MAX_ID];
    }

    /**
     * El identificador del grupo llega como parámetro de ruta, no en el cuerpo.
     */
    public function validationData(): array
    {
        return array_merge(
            parent::validationData(),
            $this->route()->parameters()
        );
    }

    /** @return array<string, string> */
    protected function groupIdMessages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'id_grupo.max' => 'El identificador del grupo no es válido.',
        ];
    }
}
