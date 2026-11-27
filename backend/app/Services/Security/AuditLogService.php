<?php

namespace App\Services\Security;

use App\Models\Action;
use App\Models\AuditLog;
use App\Support\Audit\AuditContext;
use LogicException;

class AuditLogService
{
    /** Campos que NUNCA deben quedar guardados en la bitácora. */
    private const CAMPOS_SENSIBLES = ['contrasenia', 'password', 'remember_token'];

    private AuditContext $context;

    public function __construct(?AuditContext $context = null)
    {
        $this->context = $context ?? app(AuditContext::class);
    }

    /**
     * @param  int|null  $idSesion  sesión de la operación (log.id_sesion); por defecto, la de la petición en curso
     */
    public function registrar(
        string $operacion,
        string $tabla,
        ?array $antes,
        ?array $despues,
        string $idUsuario,
        ?int $idSesion = null
    ): void {
        AuditLog::create([
            'id_accion'      => $this->resolverAccion($operacion),
            'antiguo_valor'  => $this->limpiar($antes),
            'nuevo_valor'    => $this->limpiar($despues),
            'tabla_afectada' => $tabla,
            'id_usuario'     => $idUsuario,
            'id_sesion'      => $idSesion ?? $this->context->sessionId(),
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
