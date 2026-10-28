<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta del reporte final de un examen (HU-051): solo la hace un docente.
 *
 * Aquí solo se valida el rol. Que el examen sea del docente y esté finalizado
 * lo decide FinalReportService.
 */
class FinalReportRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('El reporte final solo está disponible para sus exámenes finalizados.');
    }

    public function rules(): array
    {
        return [];
    }
}