<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Services\Academic\SubjectCatalogService;
use Illuminate\Http\JsonResponse;

/**
 * Lista los períodos académicos disponibles, indicando cuál está activo.
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
        $periods = Period::query()->orderByDesc('gestion')->orderByDesc('nombre_periodo')->get();

        $activePeriodId = $this->subjectCatalog->activePeriodId();

        return response()->json([
            'data' => $periods->map(fn (Period $period) => [
                'id_periodo' => (int) $period->id_periodo,
                'nombre_periodo' => $period->nombre_periodo,
                'gestion' => (int) $period->gestion,
            ]),
            'meta' => [
                'id_periodo_activo' => $activePeriodId > 0 ? $activePeriodId : null,
            ],
        ]);
    }
}