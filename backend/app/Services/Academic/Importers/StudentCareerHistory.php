<?php

namespace App\Services\Academic\Importers;

use Illuminate\Support\Facades\DB;

/**
 * Carrera con la que cada estudiante ya figura en el sistema (HU-021, multicarrera).
 *
 * En la UMSS un estudiante cursa una sola carrera a la vez, así que su inscripción más reciente
 * dice cuál es. Si cambió de carrera, la más reciente es la nueva.
 *
 * Una inscripción sin carrera (cargada por fuera de la nómina: datos de prueba, scripts) cuenta con
 * la carrera de su grupo solo si el grupo atiende una sola, porque ahí no hay duda. En un grupo de
 * varias carreras no se adivina: esa inscripción no dice nada y se mira la anterior.
 */
class StudentCareerHistory
{
    /** Cuántos códigos SIS van en cada consulta: una nómina admite miles de filas. */
    private const CHUNK_SIZE = 1000;

    /** Carrera de la inscripción; sin ella, la del grupo si atiende una sola; si no, NULL. */
    private const KNOWN_CAREER = 'COALESCE(grupo_estudiante.id_carrera, CASE WHEN '
        . '(SELECT count(*) FROM grupo_oferta WHERE grupo_oferta.id_grupo = grupo.id_grupo) <= 1 '
        . 'THEN grupo.id_carrera END)';

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
                ->join('grupo', 'grupo.id_grupo', '=', 'grupo_estudiante.id_grupo')
                ->whereIn('estudiante.cod_sis', $chunk)
                ->whereRaw(self::KNOWN_CAREER . ' IS NOT NULL')
                ->selectRaw(
                    'DISTINCT ON (estudiante.cod_sis) estudiante.cod_sis, ' . self::KNOWN_CAREER . ' AS id_carrera'
                )
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
