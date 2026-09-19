<?php

namespace App\Services\Security;

use App\Models\Action;
use App\Models\AuditLog;

class AuditLogService
{
    /** Campos que NUNCA deben quedar guardados en la bitácora. */
    private const CAMPOS_SENSIBLES = ['contrasenia', 'password', 'remember_token'];

    public function registrar(
        string $operacion,
        string $tabla,
        ?array $antes,
        ?array $despues,
        ?string $idUsuario = null
    ): void {
        AuditLog::create([
            'id_accion'      => $this->resolverAccion($operacion),
            'antiguo_valor'  => $this->limpiar($antes),
            'nuevo_valor'    => $this->limpiar($despues),
            'tabla_afectada' => $tabla,
            'id_usuario'     => $idUsuario ?? $this->usuarioActual(),
        ]);
    }

    private function resolverAccion(string $operacion): ?int
    {
        return Action::where('operacion', $operacion)->value('id_accion');
    }

    /** Quita contraseñas y datos sensibles antes de persistir. */
    private function limpiar(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        return array_diff_key($datos, array_flip(self::CAMPOS_SENSIBLES));
    }

    private function usuarioActual(): ?string
    {
        return auth()->id() ?? config('sciem.usuario_prueba');
    }
}