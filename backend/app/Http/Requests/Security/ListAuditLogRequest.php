<?php

namespace App\Http\Requests\Security;

use App\Services\Security\AuditLogQueryService;
use Illuminate\Validation\Rule;

/** Listado de la bitácora con filtros por usuario, acción, módulo y rango de fechas (La Paz). */
class ListAuditLogRequest extends AuditLogRequest
{
    public function rules(): array
    {
        $modules = array_merge(
            array_map('strval', array_keys((array) config('sciem.bitacora.modulos', []))),
            [AuditLogQueryService::OTHERS]
        );

        $rules = [
            'q' => ['nullable', 'string', 'max:100'],
            'accion' => ['nullable', 'string', 'max:100', 'regex:/^[A-Z0-9_]+$/'],
            'modulo' => ['nullable', 'string', Rule::in($modules)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1', 'max:' . AuditLogQueryService::MAX_PAGE],
        ];

        // Sin «desde» no hay con qué comparar: la regla compararía contra un campo ausente.
        if ($this->filled('desde')) {
            $rules['hasta'][] = 'after_or_equal:desde';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha de inicio debe ser anterior a la fecha de fin.',
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'usuario',
            'accion' => 'acción',
            'modulo' => 'módulo',
            'desde' => 'fecha de inicio',
            'hasta' => 'fecha de fin',
            'page' => 'página',
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->validated();
    }
}
