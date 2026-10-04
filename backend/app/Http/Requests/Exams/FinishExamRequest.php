<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finalización manual de un examen (HU-11): solo la hace el docente, nunca un auxiliar.
 *
 * Aquí solo se valida el rol. Que el examen sea del docente y esté activo lo decide
 * ExamLifecycleService. Es el mismo patrón de HU-09 (se rechaza al Auxiliar, no se exige Docente).
 */
class FinishExamRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return ! $roles->isAssistant($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Un auxiliar no puede finalizar un examen.');
    }

    public function rules(): array
    {
        return [];
    }
}
