<?php

namespace App\Http\Requests\Exams;

use App\Services\Security\UserRoleService;
use App\Support\CurrentUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class PreviewEligibleListRequest extends FormRequest
{
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
            'archivo' => [
                'bail',
                'required',
                'file',
                'max:10240',
                function (string $attribute, $value, $fail): void {
                    $extension = mb_strtolower(
                        $value->getClientOriginalExtension(),
                        'UTF-8'
                    );

                    if (!in_array($extension, ['csv', 'xlsx'], true)) {
                        $fail('El archivo debe tener formato CSV o XLSX.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo.',
            'archivo.file' => 'El archivo debe enviarse como archivo.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
            'archivo.uploaded' => 'No se pudo recibir el archivo. Verifique que no supere los 10 MB '
                . 'y vuelva a intentarlo.',
        ];
    }
}
