<?php

namespace App\Services\Security;

use App\Models\Action;
use App\Models\AuditLog;
use LogicException;

class AuditLogService
{
    /** Campos que NUNCA deben quedar guardados en la bitácora. */
    private const CAMPOS_SENSIBLES = ['contrasenia', 'password', 'remember_token'];

    public function registrar(
        string $operacion,
        string $tabla,
        ?array $antes,
        ?array $despues,
        string $idUsuario
    ): void {
        AuditLog::create([
            'id_accion'      => $this->resolverAccion($operacion),
            'antiguo_valor'  => $this->limpiar($antes),
            'nuevo_valor'    => $this->limpiar($despues),
            'tabla_afectada' => $tabla,
            'id_usuario'     => $idUsuario,
        ]);
    }


private function resolverAccion(string $operacion): int
{
    $idAccion = Action::where('operacion', $operacion)
        ->value('id_accion');

    if ($idAccion === null) {
        throw new LogicException(
            "La operación de auditoría '{$operacion}' no está registrada."
        );
    }

    return (int) $idAccion;
}


    /** Quita contraseñas y datos sensibles antes de persistir. */
    private function limpiar(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        return array_diff_key($datos, array_flip(self::CAMPOS_SENSIBLES));
    }
}
