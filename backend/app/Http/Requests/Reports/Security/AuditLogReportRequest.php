<?php

namespace App\Http\Requests\Reports\Security;

use App\Http\Requests\Security\ListAuditLogRequest;

/**
 * HU-068: validación de filtros del reporte de bitácora.
 * El rango de fechas es obligatorio y la autorización se
 * hereda de la consulta de bitácora.
 */
class AuditLogReportRequest extends ListAuditLogRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['desde'] = [
            'required',
            'date_format:Y-m-d',
        ];

        $rules['hasta'] = [
            'required',
            'date_format:Y-m-d',
            'after_or_equal:desde',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'desde.required' => 'La fecha de inicio es obligatoria.',
            'hasta.required' => 'La fecha de fin es obligatoria.',
        ]);
    }
}
