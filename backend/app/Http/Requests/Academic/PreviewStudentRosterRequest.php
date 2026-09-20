<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class PreviewStudentRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_grupo' => [
                'required',
                'integer',
                'min:1',
            ],
            'archivo' => [
                'required',
                'file',
                'max:10240',
                function (
                    string $attribute,
                    $value,
                    $fail
                ): void {
                    $extension = mb_strtolower(
                        $value->getClientOriginalExtension(),
                        'UTF-8'
                    );

                    if (!in_array(
                        $extension,
                        ['csv', 'xlsx'],
                        true
                    )) {
                        $fail(
                            'El archivo debe tener formato CSV o XLSX.'
                        );
                    }
                },
            ],
        ];
    }

    public function validationData(): array
    {
        return array_merge(
            parent::validationData(),
            $this->route()->parameters()
        );
    }

    public function messages(): array
    {
        return [
            'id_grupo.required' => 'Debe indicarse el grupo.',
            'id_grupo.integer' => 'El identificador del grupo debe ser numérico.',
            'id_grupo.min' => 'El identificador del grupo no es válido.',
            'archivo.required' => 'Debe seleccionar una nómina.',
            'archivo.file' => 'La nómina debe enviarse como archivo.',
            'archivo.max' => 'La nómina no puede superar los 10 MB.',
        ];
    }
}