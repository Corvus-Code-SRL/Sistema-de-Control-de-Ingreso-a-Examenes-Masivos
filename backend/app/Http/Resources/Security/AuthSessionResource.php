<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sesión abierta: la cuenta, su rol vigente y, según el caso, el token (login) o la navegación (yo).
 *
 * El recurso envuelve el arreglo que arma AuthService.
 */
class AuthSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        $session = $this->resource;
        $user = $session['usuario'];
        $role = $session['rol'];

        $data = [
            'usuario' => [
                'id_usuario' => $user->id_usuario,
                'cod_sis' => $user->cod_sis,
                'nombre' => $user->nombre,
                'apellido_paterno' => $user->apellido_paterno,
                'apellido_materno' => $user->apellido_materno,
                'nombre_completo' => $user->nombre_completo,
                'correo' => $user->correo,
                'estado' => $user->estado,
            ],
            'rol' => [
                'id_rol' => $role->id_rol,
                'nombre_rol' => $role->nombre_rol,
            ],
        ];

        if (array_key_exists('token', $session)) {
            $data['token'] = $session['token'];
            $data['tipo_token'] = 'Bearer';
            $data['expira_en'] = optional($session['expira_en'])->toIso8601String();
        }

        if (array_key_exists('navegacion', $session)) {
            $data['navegacion'] = $session['navegacion'];
            $data['password_confirmado_en'] = optional($session['password_confirmado_en'])->toIso8601String();
        }

        return $data;
    }
}
