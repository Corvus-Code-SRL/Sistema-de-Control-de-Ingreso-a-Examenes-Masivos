<?php

namespace App\Support;

/**
 * Autor de las escrituras que no hace ninguna persona con sesión: seeders y comandos.
 *
 * Es la cuenta de config('sciem.usuario_prueba'), la que UserSeeder siembra para que la bitácora
 * tenga un autor válido (log.id_usuario es NOT NULL). NO es un usuario de la API: ningún
 * Controller, Request, Policy ni Service la usa; lo vigila SystemActorOnlyForSeedersTest.
 */
class SystemActor
{
    /** Id de la cuenta de sistema; null si la configuración lo dejó vacío. */
    public function id(): ?string
    {
        $id = config('sciem.usuario_prueba');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
