<?php

namespace App\Services\Academic\Importers;

use Illuminate\Support\Facades\DB;

/**
 * Carrera con la que cada estudiante ya figura en el sistema (HU-021, multicarrera).
 *
 * En la UMSS un estudiante cursa una sola carrera a la vez, así que su inscripción más reciente
 * con carrera dice cuál es. Si cambió de carrera, la más reciente es la nueva.
 */
class StudentCareerHistory
{
    /** Cuántos códigos SIS van en cada consulta: una nómina admite miles de filas. */
    private const CHUNK_SIZE = 1000;

    /**
     * @param  array<int, string>  $sisCodes  códigos SIS normalizados
     * @return array<string, int>  código SIS => id_carrera de su inscripción más reciente
     */
    public function latestCareerBySis(array $sisCodes): array
    {
        $careers = [];

        foreach (array_chunk(array_values(array_unique($sisCodes)), self::CHUNK_SIZE) as $chunk) {
            $rows = DB::table('grupo_estudiante')
                ->join('estudiante', 'estudiante.id_estudiante', '=', 'grupo_estudiante.id_estudiante')
                ->whereIn('estudiante.cod_sis', $chunk)
                ->whereNotNull('grupo_estudiante.id_carrera')
                ->selectRaw('DISTINCT ON (estudiante.cod_sis) estudiante.cod_sis, grupo_estudiante.id_carrera')
                ->orderBy('estudiante.cod_sis')
                ->orderByDesc('grupo_estudiante.fecha_inscripcion')
                ->orderByDesc('grupo_estudiante.id_grupo')
                ->get();

            foreach ($rows as $row) {
                $careers[(string) $row->cod_sis] = (int) $row->id_carrera;
            }
        }

        return $careers;
    }
}
