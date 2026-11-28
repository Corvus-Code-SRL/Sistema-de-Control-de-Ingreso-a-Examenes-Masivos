<?php

namespace App\Services\Academic;

use App\Http\Resources\Academic\UnassignableSubjectResource;
use App\Models\AcademicOffering;
use App\Models\Group;
use App\Models\Period;
use App\Services\Security\AuditLogService;
use App\Support\Database\AccentInsensitiveSearch;
use App\Support\RecordStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-008: desvincular una materia de una carrera en el período vigente.
 *
 * La desvinculación NO borra la oferta: cambia `oferta_academica.estado` de ACTIVO a INACTIVO.
 * Solo se permite en el período vigente y si la materia no tiene grupos registrados en ese período.
 * `materia_carrera` no se toca: el par sigue ACTIVO, solo deja de estar ofertado en el período.
 */
class SubjectCareerUnassignmentService
{
    private SubjectCatalogService $catalog;
    private AuditLogService $auditLog;

    public function __construct(SubjectCatalogService $catalog, AuditLogService $auditLog)
    {
        $this->catalog = $catalog;
        $this->auditLog = $auditLog;
    }

    /**
     * @return array{materias: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(int $carreraId, ?int $periodoId, ?string $buscar, int $page, int $perPage): array
    {
        $periodoId ??= $this->catalog->activePeriodId();

        $query = AcademicOffering::query()
            ->join('materia', 'materia.id_materia', '=', 'oferta_academica.id_materia')
            ->leftJoin('grupo', function ($join) {
                $join->on('grupo.id_carrera', '=', 'oferta_academica.id_carrera')
                    ->on('grupo.id_materia', '=', 'oferta_academica.id_materia')
                    ->on('grupo.id_gestion_periodo', '=', 'oferta_academica.id_gestion_periodo')
                    ->where('grupo.estado', '=', RecordStatus::ACTIVE);
            })
            ->where('oferta_academica.id_carrera', $carreraId)
            ->where('oferta_academica.id_gestion_periodo', $periodoId)
            ->select([
                'oferta_academica.id_carrera',
                'oferta_academica.id_materia',
                'materia.codigo',
                'materia.nombre',
                'oferta_academica.estado',
            ])
            ->selectRaw('count(grupo.id_grupo) as grupos_count')
            ->groupBy(
                'oferta_academica.id_carrera',
                'oferta_academica.id_materia',
                'materia.codigo',
                'materia.nombre',
                'oferta_academica.estado'
            );

        $termino = $buscar !== null ? AccentInsensitiveSearch::normalize($buscar) : '';
        if ($termino !== '') {
            $patron = AccentInsensitiveSearch::pattern($termino);
            $query->where(function ($q) use ($patron) {
                $q->whereRaw(AccentInsensitiveSearch::condition('materia.nombre'), [$patron])
                    ->orWhereRaw('materia.codigo ILIKE ?', [$patron]);
            });
        }

        $page = $query
            ->orderBy('materia.codigo')
            ->orderBy('oferta_academica.id_materia')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'materias' => UnassignableSubjectResource::collection($page->getCollection())->resolve(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
        ];
    }

    /**
     * Desvincula la oferta (estado → INACTIVO) y audita. Devuelve la oferta actualizada.
     *
     * @throws ValidationException
     */
    public function unassign(int $carreraId, int $periodoId, int $materiaId, string $actorId): AcademicOffering
    {
        return DB::transaction(function () use ($carreraId, $periodoId, $materiaId, $actorId) {
            $period = Period::query()->sharedLock()->find($periodoId);
            $this->assertPeriodIsActive($period);
            $this->assertPeriodIsCurrent($period);

            $oferta = AcademicOffering::query()
                ->where('id_carrera', $carreraId)
                ->where('id_materia', $materiaId)
                ->where('id_gestion_periodo', $periodoId)
                ->lockForUpdate()
                ->first();

            if ($oferta === null) {
                throw ValidationException::withMessages([
                    'id_materia' => ['La materia no está asignada a esta carrera en este período.'],
                ]);
            }

            if ($oferta->estado === RecordStatus::INACTIVE) {
                throw ValidationException::withMessages([
                    'id_materia' => ['La materia ya está desvinculada en este período.'],
                ]);
            }

            $grupos = Group::query()
                ->where('id_carrera', $carreraId)
                ->where('id_materia', $materiaId)
                ->where('id_gestion_periodo', $periodoId)
                ->where('estado', RecordStatus::ACTIVE)
                ->count();

            if ($grupos > 0) {
                throw ValidationException::withMessages([
                    'id_materia' => [
                        "No se puede desvincular: la materia tiene {$grupos} grupos registrados en este período.",
                    ],
                ]);
            }

            $antes = [
                'id_carrera' => $carreraId,
                'id_materia' => $materiaId,
                'id_gestion_periodo' => $periodoId,
                'estado' => $oferta->estado,
            ];

            AcademicOffering::query()
                ->where('id_carrera', $carreraId)
                ->where('id_materia', $materiaId)
                ->where('id_gestion_periodo', $periodoId)
                ->update(['estado' => RecordStatus::INACTIVE]);

            $oferta->estado = RecordStatus::INACTIVE;

            $despues = [
                'id_carrera' => $carreraId,
                'id_materia' => $materiaId,
                'id_gestion_periodo' => $periodoId,
                'estado' => $oferta->estado,
            ];

            $this->auditLog->registrar(
                'DESVINCULAR_MATERIA_CARRERA',
                'oferta_academica',
                $antes,
                $despues,
                $actorId
            );

            return $oferta;
        });
    }

    private function assertPeriodIsActive(?Period $period): void
    {
        if ($period !== null && $period->estado === RecordStatus::ACTIVE) {
            return;
        }

        throw ValidationException::withMessages([
            'id_periodo' => ['El período seleccionado no existe o no está activo.'],
        ]);
    }

    private function assertPeriodIsCurrent(Period $period): void
    {
        if ($period->vigente) {
            return;
        }

        throw ValidationException::withMessages([
            'id_periodo' => ['Solo se puede desvincular en el período vigente.'],
        ]);
    }
}
