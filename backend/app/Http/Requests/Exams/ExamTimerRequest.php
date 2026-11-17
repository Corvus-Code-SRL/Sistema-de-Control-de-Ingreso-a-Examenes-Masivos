<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ExamTimerRequest extends FormRequest
{
    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->canUse($currentUser->id(), 'exam_timer');
    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException('Solo el docente responsable del examen puede controlar su tiempo');
    }

    public function rules(): array
    {
        return $this->isMethod('GET') ? [] : ['minutos' => ['required', 'integer', 'between:1,1439']];
    }

    public function messages(): array
    {
        return ['minutos.*' => 'Indique un número entero de minutos entre 1 y 1439.'];
    }
}
