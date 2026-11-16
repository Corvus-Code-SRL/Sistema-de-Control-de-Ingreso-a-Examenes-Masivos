<?php

namespace App\Http\Requests\Academic;

class PreviewStudentRosterRequest extends RosterManagementRequest
{
    public function rules(): array
    {
        return [
            'id_grupo' => $this->groupIdRules(),
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
            // HU-021 multicarrera: carrera de las filas que no traen la columna «carrera».
            'id_carrera' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_ID],
        ];
    }

    public function defaultCareerId(): ?int
    {
        $careerId = $this->validated()['id_carrera'] ?? null;

        return $careerId === null ? null : (int) $careerId;
    }

    public function messages(): array
    {
        return $this->groupIdMessages() + [
            'archivo.required' => 'Debe seleccionar una nómina.',
            'archivo.file' => 'La nómina debe enviarse como archivo.',
            'archivo.max' => 'La nómina no puede superar los 10 MB.',
            // PHP descarta el archivo antes de Laravel si pasa de upload_max_filesize (12 MB).
            'archivo.uploaded' => 'No se pudo recibir la nómina. Verifique que no supere los 10 MB '
                . 'y vuelva a intentarlo.',
            'id_carrera.integer' => 'El identificador de la carrera debe ser numérico.',
            'id_carrera.min' => 'El identificador de la carrera no es válido.',
            'id_carrera.max' => 'El identificador de la carrera no es válido.',
        ];
    }
}
