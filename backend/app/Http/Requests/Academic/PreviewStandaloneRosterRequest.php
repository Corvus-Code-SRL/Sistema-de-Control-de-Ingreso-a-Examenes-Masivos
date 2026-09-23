<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class PreviewStandaloneRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'bail',
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

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar una nómina.',
            'archivo.file' => 'La nómina debe enviarse como archivo.',
            'archivo.max' => 'La nómina no puede superar los 10 MB.',
        ];
    }
}