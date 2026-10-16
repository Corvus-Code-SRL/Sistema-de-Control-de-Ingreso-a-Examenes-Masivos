<?php

namespace App\Services\RiskCenter\Academic;

use Illuminate\Support\Facades\DB;

/** Consulta el resumen de antecedentes académicos sin exponer la Central de Riesgo por API. */
class RiskCenterQueryService
{
    public function summaryForStudent(int $studentId): array
    {
        $latest = DB::table('central_riesgo as cr')
            ->join('reporte_estudiante as re', 're.id_reporte_est', '=', 'cr.id_reporte_est')
            ->join('tipo_falta as tf', 'tf.id_falta', '=', 're.id_tipo_falta')
            ->where('cr.id_estudiante', $studentId)
            ->selectRaw('count(*) over() as total, tf.nombre as tipo_falta, cr.fecha_registro')
            ->orderByDesc('cr.fecha_registro')
            ->orderByDesc('cr.id_reporte_est')
            ->first();

        return [
            'total' => (int) ($latest->total ?? 0),
            'latest' => $latest === null ? null : [
                'tipo_falta' => $latest->tipo_falta,
                'fecha_registro' => substr((string) $latest->fecha_registro, 0, 10),
            ],
        ];
    }

    /** Prepara todos los resúmenes en una sola consulta al abrir el control. */
    public function summariesForSnapshot(): array
    {
        $rows = DB::table('central_riesgo as cr')
            ->join('reporte_estudiante as re', 're.id_reporte_est', '=', 'cr.id_reporte_est')
            ->join('tipo_falta as tf', 'tf.id_falta', '=', 're.id_tipo_falta')
            ->orderBy('cr.id_estudiante')
            ->orderByDesc('cr.fecha_registro')
            ->orderByDesc('cr.id_reporte_est')
            ->get(['cr.id_estudiante', 'tf.nombre', 'cr.fecha_registro']);

        $summaries = [];
        foreach ($rows as $row) {
            $studentId = (int) $row->id_estudiante;
            if (! isset($summaries[$studentId])) {
                $summaries[$studentId] = [
                    'total' => 0,
                    'latest' => [
                        'tipo_falta' => $row->nombre,
                        'fecha_registro' => substr((string) $row->fecha_registro, 0, 10),
                    ],
                ];
            }
            $summaries[$studentId]['total']++;
        }

        return $summaries;
    }
}
