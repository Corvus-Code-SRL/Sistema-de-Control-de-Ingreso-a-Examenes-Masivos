<?php

namespace App\Services\Exams;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Consulta en tiempo real de los contadores y ultimos ingresos
class RedControlService
{
    // Busqueda en Redis , si no existe ejecutamos las consultas SQL
    public function currentStatus($examId, ?string $clientVersion)
    {
        // CORRECCIÓN 1: inyectamos $examId correctamente
        $payload = Cache::remember("examen_{$examId}_estado", 2, function() use($examId) {
            
            // Contadores agregados 
            $totals = DB::table('examen_estudiante')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN estado_ingreso = 'INGRESO' THEN 1 ELSE 0 END) AS ingresados,
                    SUM(CASE WHEN estado_ingreso = 'NO_INGRESO' THEN 1 ELSE 0 END) AS pendientes
                ")
                ->where('id_examen', $examId)
                ->first();
            
            // Ultimos 10 estudiantes que ingresaron
            $latestEntries = DB::table('examen_estudiante as ee')
                ->join('estudiante as est', 'ee.id_estudiante', '=', 'est.id_estudiante')
                ->where('ee.id_examen', $examId)
                ->orderByDesc('ee.hora_ingreso')
                ->limit(10)
                ->get([
                    'est.nombre',
                    'est.apellido_paterno',
                    'ee.hora_ingreso',
                ]);
            
            return [
                'ingresados'       => (int) $totals->ingresados,
                'pendientes'       => (int) $totals->pendientes,
                'total'            => (int) $totals->total,
                'ultimos_ingresos' => $latestEntries,
                'version'          => (string) now()->timestamp, // CORRECCIÓN 4: Paréntesis añadidos
            ];
        });

        // Ahorro de datos Polling
        if ($clientVersion !== null && $clientVersion === $payload['version']) {
            return [
                'sin_cambios' => true,
                'version'     => $payload['version']
            ];
        }

        return $payload;
    }

    // Invalida la Cache, debe ser invocado externamente tras cada registro de ingreso.
    public function notifyChange(int $examId): void
    {
        // IMPORTANTE: También actualicé la llave aquí para que coincida con la que definiste arriba
        Cache::forget("examen_{$examId}_estado");
    }
}