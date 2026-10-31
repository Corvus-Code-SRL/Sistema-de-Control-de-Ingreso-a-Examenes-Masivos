<?php

namespace App\Http\Resources\Security;

/**
 * Detalle de una cuenta: sus datos más el historial completo de roles.
 */
class UserDetailResource extends UserResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'historial_roles' => RoleHistoryResource::collection($this->roles),
        ]);
    }
}
