<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelación de un examen programado (HU-24): solo la hace un docente activo.
 *
 * Aquí solo se valida el rol. Que el examen sea del docente y siga programado lo decide
 * ExamService.
 */
class CancelExamRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede cancelar un examen.');
    }

    public function rules(): array
    {
        return [];
    }
}
