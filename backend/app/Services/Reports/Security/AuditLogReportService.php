<?php

namespace App\Services\Reports\Security;

use App\Models\User;
use App\Services\Security\AuditLogQueryService;
use Illuminate\Validation\ValidationException;

/**
 * HU-068: audit log report.
 */
class AuditLogReportService
{
    private AuditLogQueryService $auditLog;

    public function __construct(AuditLogQueryService $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /**
     * Generate the paginated report and its global totals.
     */
    public function generate(array $filters): array
    {
        $totals = $this->auditLog->reportTotals($filters);

        return [
            'registros' => $this->auditLog->paginate($filters),
            'totales' => $totals,
            'filtros' => $this->reportFilters($filters),
            'puede_exportar' => $totals['total_operaciones'] > 0,
        ];
    }

    /**
     * Generate report data without screen pagination.
     */
    public function export(array $filters, string $actorId): array
    {
        $filters = $this->reportFilters($filters);

        $actor = User::query()->findOrFail($actorId);

        $totals = $this->auditLog->reportTotals($filters);

        if ($totals['total_operaciones'] === 0) {
            throw ValidationException::withMessages([
                'reporte' => [
                    'No existen operaciones para los filtros seleccionados.',
                ],
            ]);
        }

        $options = $this->auditLog->options();

        $actionLabels = array_column(
            $options['acciones'],
            'etiqueta',
            'codigo'
        );

        $moduleLabels = array_column(
            $options['modulos'],
            'etiqueta',
            'clave'
        );

        $action = $filters['accion'];
        $module = $filters['modulo'];

        return [
            'cabecera' => [
                'generado_por' => $actor->nombre_completo
                    . ' (SIS: ' . $actor->cod_sis . ')',
                'generado_en' => now()->toIso8601String(),
                'accion' => $action === null
                    ? 'Todas las acciones'
                    : ($actionLabels[$action] ?? $action),
                'modulo' => $module === null
                    ? 'Todos los módulos'
                    : ($moduleLabels[$module] ?? (
                        $module === 'otros' ? 'Otros' : $module
                    )),
            ],
            'filtros' => $filters,
            'totales' => $totals,
            'registros' => $this->auditLog->reportRows($filters),
        ];
    }

    /**
     * Keep only the supported report filters.
     */
    private function reportFilters(array $filters): array
    {
        return [
            'desde' => $filters['desde'],
            'hasta' => $filters['hasta'],
            'q' => $filters['q'] ?? null,
            'accion' => $filters['accion'] ?? null,
            'modulo' => $filters['modulo'] ?? null,
        ];
    }
}