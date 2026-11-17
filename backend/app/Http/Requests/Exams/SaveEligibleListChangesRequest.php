<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use App\Support\MotivoNoHabilitacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEligibleListChangesRequest extends FormRequest
{
    private const MAX_ID = 2147483647;

    public function authorize(CurrentUser $currentUser, UserRoleService $roles): bool
    {
        return $roles->isTeacher($currentUser->id());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para ver esta sección.');
    }

    public function rules(): array
    {
        return [
            'cambios' => ['required', 'array', 'min:1'],
            'cambios.*.id_estudiante' => [
                'bail',
                'required',
                'integer',
                'min:1',
                'max:' . self::MAX_ID,
                'distinct',
            ],
            'cambios.*.estado_habilitacion' => [
                'required',
                'string',
                Rule::in(['HABILITADO', 'NO_HABILITADO']),
            ],
            'cambios.*.motivo' => [
                'required_if:cambios.*.estado_habilitacion,NO_HABILITADO',
                'nullable',
                'string',
                Rule::in(MotivoNoHabilitacion::all()),
            ],
            'cambios.*.detalle' => [
                'required_if:cambios.*.motivo,' . MotivoNoHabilitacion::OTRO,
                'nullable',
                'string',
                'min:10',
                'max:500',
                'not_regex:/^:/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cambios.required' => 'Debe indicarse al menos un cambio.',
            'cambios.array' => 'Los cambios deben enviarse como una lista.',
            'cambios.min' => 'Debe indicarse al menos un cambio.',
            'cambios.*.id_estudiante.required' => 'Cada cambio debe indicar el estudiante.',
            'cambios.*.id_estudiante.integer' => 'El estudiante indicado no es válido.',
            'cambios.*.id_estudiante.distinct' => 'Un estudiante no puede repetirse en los cambios.',
            'cambios.*.estado_habilitacion.required' => 'Cada cambio debe indicar el estado.',
            'cambios.*.estado_habilitacion.in' => 'El estado debe ser HABILITADO o NO_HABILITADO.',
            'cambios.*.motivo.required_if' => 'Debe indicarse el motivo al no habilitar.',
            'cambios.*.motivo.in' => 'El motivo indicado no es válido.',
            'cambios.*.detalle.required_if' => 'El motivo OTRO exige un detalle.',
            'cambios.*.detalle.min' => 'El detalle debe tener al menos 10 caracteres.',
            'cambios.*.detalle.max' => 'El detalle no puede superar los 500 caracteres.',
            'cambios.*.detalle.not_regex' => 'El detalle no puede empezar con ":".',
        ];
    }
}
