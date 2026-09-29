<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\CurrentUserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class IndexClassroomRequest extends FormRequest
{
    public function authorize(CurrentUserService $currentUser): bool
    {
        return $currentUser->isAdministrator();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Solo un Administrador puede consultar el catálogo de ambientes.');
    }

    public function rules(): array
    {
        return [];
    }
}
