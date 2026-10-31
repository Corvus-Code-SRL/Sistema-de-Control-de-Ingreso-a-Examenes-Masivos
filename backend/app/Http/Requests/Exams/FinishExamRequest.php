<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finalización manual de un examen (HU-11): solo la hace un docente activo.
 *
 * Aquí solo se valida el rol. Que el examen sea del docente y esté activo lo decide
 * ExamLifecycleService.
 */
class FinishExamRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un docente puede finalizar un examen.');
    }

    public function rules(): array
    {
        return [];
    }
}
