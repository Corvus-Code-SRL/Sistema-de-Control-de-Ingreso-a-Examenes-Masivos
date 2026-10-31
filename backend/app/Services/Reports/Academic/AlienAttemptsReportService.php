<?php

namespace App\Services\Reports\Academic;

use Illuminate\Support\Facades\DB;

class AlienAttemptsReportService
{
    private const PER_PAGE = 10;
    private const MOTIVO = 'NO_PERTENECE';

    public function paginate(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $rows = $this->baseQuery($filters)->get();
        $total = $rows->count();
        $items = $rows->forPage($page, $perPage)->values();

        return [
            'data' => $items,
            'meta' => array_merge(
                [
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $items->isEmpty() ? null : (($page - 1) * $perPage) + 1,
                    'to' => $items->isEmpty() ? null : min($page * $perPage, $total),
                ],
                $this->totals($filters)
            ),
        ];
    }

    public function pdf(array $filters): array
    {
        $rows = $this->baseQuery($filters)->get();

        return [
            'cabecera' => [
                'desde' => $filters['desde'],
                'hasta' => $filters['hasta'],
                'facultad' => $filters['facultad'] ?? null,
                'generado_en' => now('UTC')->toIso8601String(),
            ],
            'meta' => $this->totals($filters),
            'estudiantes' => $rows->map(fn ($row) => [
                'nombre_completo' => trim($row->nombre_completo),
                'cod_sis' => $row->cod_sis,
                'intentos' => (int) $row->intentos,
                'examenes' => explode(' · ', $row->examenes),
                'id_cr_estudiante' => $row->id_cr_estudiante !== null ? (int) $row->id_cr_estudiante : null,
            ])->all(),
        ];
    }

    private function baseQuery(array $filters)
    {
        return DB::table('intento_ingreso as ii')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'ii.id_estudiante')
            ->join('examen as ex', 'ex.id_examen', '=', 'ii.id_examen')
            ->leftJoin('carrera as ca', 'ca.id_carrera', '=', 'ex.id_carrera')
            ->leftJoin('central_riesgo as cr', 'cr.id_estudiante', '=', 'ii.id_estudiante')
            ->where('ii.motivo', self::MOTIVO)
            ->whereBetween('ii.registrado_en', [
                $filters['desde'] . ' 00:00:00',
                $filters['hasta'] . ' 23:59:59',
            ])
            ->when($filters['facultad'] ?? null, fn ($q, $facultad) => $q->where('ca.id_facultad', $facultad))
            ->selectRaw("e.id_estudiante, e.cod_sis, COUNT(*) AS intentos, "
                . "string_agg(DISTINCT ex.nombre_examen, ' · ') AS examenes, "
                . "MIN(cr.id_cr_estudiante) AS id_cr_estudiante, "
                . "CONCAT_WS(' ', e.nombre, e.apellido_paterno, e.apellido_materno) AS nombre_completo")
            ->groupBy('e.id_estudiante', 'e.cod_sis', 'e.nombre', 'e.apellido_paterno', 'e.apellido_materno')
            ->orderByDesc('intentos')
            ->orderBy('e.apellido_paterno')
            ->orderBy('e.apellido_materno')
            ->orderBy('e.nombre');
    }

    private function totals(array $filters): array
    {
        $base = DB::table('intento_ingreso as ii')
            ->join('examen as ex', 'ex.id_examen', '=', 'ii.id_examen')
            ->leftJoin('carrera as ca', 'ca.id_carrera', '=', 'ex.id_carrera')
            ->where('ii.motivo', self::MOTIVO)
            ->whereBetween('ii.registrado_en', [
                $filters['desde'] . ' 00:00:00',
                $filters['hasta'] . ' 23:59:59',
            ])
            ->when($filters['facultad'] ?? null, fn ($q, $facultad) => $q->where('ca.id_facultad', $facultad));

        return [
            'total_intentos' => (clone $base)->count(),
            'total_estudiantes' => (clone $base)->distinct()->count('ii.id_estudiante'),
            'total_en_central_riesgo' => (clone $base)
                ->join('central_riesgo as cr', 'cr.id_estudiante', '=', 'ii.id_estudiante')
                ->distinct()
                ->count('ii.id_estudiante'),
        ];
    }
}
