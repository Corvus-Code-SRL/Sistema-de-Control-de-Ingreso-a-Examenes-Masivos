<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $rol = $this->rolActivo();

        return [
            'id_usuario'       => $this->id_usuario,
            'cod_sis'          => $this->cod_sis,
            'nombre'           => $this->nombre,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'nombre_completo'  => $this->nombre_completo,
            'correo'           => $this->correo,
            'estado'           => $this->estado,

            // CA 5: una cuenta recién creada devuelve null acá.
            // El frontend lo usa para pintar el badge "Sin rol".
            'rol' => $rol ? [
                'id_rol'     => $rol->id_rol,
                'nombre_rol' => $rol->nombre_rol,
            ] : null,
        ];
    }
}