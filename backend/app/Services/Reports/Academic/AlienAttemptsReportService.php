<?php

namespace App\Services\Reports\Academic;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AlienAttemptsReportService
{
    private const PER_PAGE = 10;
    private const MOTIVO = 'NO_PERTENECE';

    public function paginate(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $total = $this->countStudents($filters);
        $estudianteIds = $this->pageStudentIds($filters, $page, $perPage);
        $items = $this->rowsFor($filters, $estudianteIds);

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
                'facultad' => $this->facultyName($filters['facultad'] ?? null),
                'generado_en' => now($this->timezone())->toIso8601String(),
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

    private function pageStudentIds(array $filters, int $page, int $perPage): array
    {
        return DB::table('intento_ingreso as ii')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'ii.id_estudiante')
            ->join('examen as ex', 'ex.id_examen', '=', 'ii.id_examen')
            ->leftJoin('carrera as ca', 'ca.id_carrera', '=', 'ex.id_carrera')
            ->where('ii.motivo', self::MOTIVO)
            ->where(fn ($q) => $this->whereInRange($q, $filters))
            ->when($filters['facultad'] ?? null, fn ($q, $facultad) => $q->where('ca.id_facultad', $facultad))
            ->groupBy('e.id_estudiante', 'e.apellido_paterno', 'e.apellido_materno', 'e.nombre')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('e.apellido_paterno')
            ->orderBy('e.apellido_materno')
            ->orderBy('e.nombre')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->pluck('e.id_estudiante')
            ->all();
    }

    private function countStudents(array $filters): int
    {
        return DB::table('intento_ingreso as ii')
            ->join('examen as ex', 'ex.id_examen', '=', 'ii.id_examen')
            ->leftJoin('carrera as ca', 'ca.id_carrera', '=', 'ex.id_carrera')
            ->where('ii.motivo', self::MOTIVO)
            ->where(fn ($q) => $this->whereInRange($q, $filters))
            ->when($filters['facultad'] ?? null, fn ($q, $facultad) => $q->where('ca.id_facultad', $facultad))
            ->distinct()
            ->count('ii.id_estudiante');
    }

    private function rowsFor(array $filters, array $estudianteIds)
    {
        if ($estudianteIds === []) {
            return collect();
        }

        return $this->baseQuery($filters)
            ->whereIn('ii.id_estudiante', $estudianteIds)
            ->get();
    }

    private function baseQuery(array $filters)
    {
        return DB::table('intento_ingreso as ii')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'ii.id_estudiante')
            ->join('examen as ex', 'ex.id_examen', '=', 'ii.id_examen')
            ->leftJoin('carrera as ca', 'ca.id_carrera', '=', 'ex.id_carrera')
            ->where('ii.motivo', self::MOTIVO)
            ->where(fn ($q) => $this->whereInRange($q, $filters))
            ->when($filters['facultad'] ?? null, fn ($q, $facultad) => $q->where('ca.id_facultad', $facultad))
            ->selectRaw("e.id_estudiante, e.cod_sis, COUNT(*) AS intentos, "
                . "string_agg(DISTINCT ex.nombre_examen, ' · ') AS examenes, "
                . "(SELECT MIN(cr.id_cr_estudiante) FROM central_riesgo cr "
                . "WHERE cr.id_estudiante = e.id_estudiante) AS id_cr_estudiante, "
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
            ->where(fn ($q) => $this->whereInRange($q, $filters))
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

    /**
     * Intervalo semiabierto [desde, hasta + 1 día) en hora de La Paz, expresado en UTC con desfase explícito
     * para que el resultado no dependa de la zona horaria de la sesión de PostgreSQL.
     */
    private function whereInRange($query, array $filters): void
    {
        $tz = $this->timezone();
        $from = Carbon::parse($filters['desde'], $tz)->startOfDay();
        $until = Carbon::parse($filters['hasta'], $tz)->addDay()->startOfDay();

        $query->where('ii.registrado_en', '>=', $from->utc()->format('Y-m-d H:i:sP'))
            ->where('ii.registrado_en', '<', $until->utc()->format('Y-m-d H:i:sP'));
    }

    private function timezone(): string
    {
        return config('sciem.zona_horaria', 'America/La_Paz');
    }

    private function facultyName(?int $id): string
    {
        if ($id === null) {
            return 'Todas';
        }

        $name = DB::table('facultad')->where('id_facultad', $id)->value('nombre');

        return $name ?? 'Todas';
    }
}
