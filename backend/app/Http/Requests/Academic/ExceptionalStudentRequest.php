<?php

namespace App\Http\Requests\Academic;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones de estudiantes excepcionales (HU-023).
 *
 * Quien los registra es una cuenta activa cuyo rol ejerce la función special_cases (casos
 * especiales, config sciem.access). Un Docente invitado, un Auxiliar, un Administrador o una cuenta
 * sin rol o inactiva reciben 403. Que el examen sea del docente responsable (no de un colaborador)
 * lo decide el servicio.
 */
abstract class ExceptionalStudentRequest extends FormRequest
{
    /** Máximo de un integer de PostgreSQL: por encima la consulta falla con 500. */
    protected const MAX_ID = 2147483647;

    /** Código SIS de un estudiante: 8 o 9 dígitos. */
    protected const SIS_PATTERN = '/^\d{8,9}$/';

    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'special_cases');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede registrar estudiantes excepcionales.');
    }
}
