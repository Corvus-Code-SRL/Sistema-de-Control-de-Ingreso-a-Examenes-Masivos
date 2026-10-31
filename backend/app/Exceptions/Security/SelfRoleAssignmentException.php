<?php

namespace App\Exceptions\Security;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Un Administrador intenta modificar su propio rol.
 *
 * Se rechaza para que no quede sin acceso administrativo por error.
 */
class SelfRoleAssignmentException extends Exception
{
    protected $message = 'No puede modificar su propio rol. Pídalo a otro administrador.';

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
