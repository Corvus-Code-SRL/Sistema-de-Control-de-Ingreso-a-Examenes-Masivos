<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\PreviewStandaloneRosterRequest;
use App\Services\Academic\Importers\StudentRosterPreviewService;
use Illuminate\Http\JsonResponse;

/**
 * Preview de nómina sin grupo: el grupo se crea al confirmar.
 *
 * El frontend usa este endpoint desde el modal "Nuevo grupo" para
 * previsualizar la nómina antes de que exista el grupo.
 */
class StandaloneRosterPreviewController extends Controller
{
    private StudentRosterPreviewService $previewService;

    public function __construct(StudentRosterPreviewService $previewService)
    {
        $this->previewService = $previewService;
    }

    public function preview(PreviewStandaloneRosterRequest $request): JsonResponse
    {
        $file = $request->file('archivo');
        $extension = mb_strtolower($file->getClientOriginalExtension(), 'UTF-8');

        $result = $this->previewService->generateStandalone(
            $file->getRealPath(),
            $extension
        );

        $filas = array_map(
            static fn (array $row): array => [
                'numero_fila' => $row['row_number'],
                'codigo_sis' => $row['sis_code'],
                'apellidos' => $row['last_names'],
                'nombres' => $row['first_names'],
                'estado' => $row['status'],
                'errores' => $row['errors'],
            ],
            $result->rows()
        );

        return response()->json([
            'data' => [
                'token' => $result->token(),
                'total_filas' => $result->totalRows(),
                'filas_validas' => $result->validRows(),
                'filas_inconsistentes' => $result->inconsistentRows(),
                'filas' => $filas,
            ],
        ]);
    }
}