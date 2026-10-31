<?php

namespace App\Http\Controllers\Academic;

use App\Exceptions\Academic\ActivePeriodNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Services\Academic\SubjectCatalogService;
use App\Support\RecordStatus;
use Illuminate\Http\JsonResponse;

/**
 * Lista los períodos académicos (gestion_periodo) disponibles, indicando cuál es el vigente.
 *
 * `id_periodo` es el id de la instancia (gestion_periodo.id_gestion_periodo), `etiqueta` la forma
 * estándar «{codigo}/{anio}» («1/2026», «3/2026»), `nombre_periodo` el nombre del catálogo
 * («Semestre 1», «Verano») y `gestion` el año. El frontend muestra la etiqueta, no la construye.
 *
 * Necesario para el selector de "Período académico" del formulario de
 * registrar/actualizar grupo (HU-18, HU-19, CA 11): el docente ve el activo
 * preseleccionado pero puede verificarlo/cambiarlo antes de confirmar.
 */
class PeriodController extends Controller
{
    private SubjectCatalogService $subjectCatalog;

    public function __construct(SubjectCatalogService $subjectCatalog)
    {
        $this->subjectCatalog = $subjectCatalog;
    }

    public function index(): JsonResponse
    {
        $periods = Period::query()
            ->withLabel()
            ->where('gestion_periodo.estado', RecordStatus::ACTIVE)
            ->orderByDesc('gestion.anio')
            ->orderByDesc('periodo.codigo')
            ->get();

        // El selector debe poder listar períodos aunque ninguno esté vigente.
        try {
            $activePeriodId = $this->subjectCatalog->activePeriodId();
        } catch (ActivePeriodNotConfiguredException $exception) {
            $activePeriodId = null;
        }

        return response()->json([
            'data' => $periods->map(fn (Period $period) => [
                'id_periodo' => (int) $period->id_gestion_periodo,
                'nombre_periodo' => $period->type_name,
                'etiqueta' => $period->label,
                'gestion' => $period->year,
                'vigente' => (bool) $period->vigente,
            ]),
            'meta' => [
                'id_periodo_activo' => $activePeriodId,
            ],
        ]);
    }
}
