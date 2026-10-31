<?php

namespace App\Http\Requests\Academic\Concerns;

use App\Support\RecordStatus;
use Illuminate\Validation\Rule;

/**
 * Regla compartida por las peticiones de HU-006: el período (gestion_periodo) sobre el que se lista
 * o se asigna es obligatorio, está acotado a int4 y debe existir y estar ACTIVO.
 */
trait ValidatesAssignmentPeriod
{
    /** @return array<int, mixed> */
    protected function periodRules(): array
    {
        return [
            'required',
            'integer',
            'min:1',
            'max:2147483647',
            Rule::exists('gestion_periodo', 'id_gestion_periodo')
                ->where('estado', RecordStatus::ACTIVE),
        ];
    }

    /** @return array<string, string> */
    protected function periodMessages(): array
    {
        return [
            'id_periodo.required' => 'Debe indicarse el período académico.',
            'id_periodo.integer' => 'El identificador del período debe ser numérico.',
            'id_periodo.min' => 'El identificador del período no es válido.',
            'id_periodo.max' => 'El identificador del período no es válido.',
            'id_periodo.exists' => 'El período seleccionado no existe o no está activo.',
        ];
    }
}
