<?php

namespace App\Services\Academic\Importers;

use App\Support\Database\AccentInsensitiveSearch;

/**
 * Asigna a cada fila de la nómina la carrera con la que el estudiante queda en el grupo
 * (HU-021, multicarrera).
 *
 * Orden de decisión por fila:
 *   1. La columna opcional «carrera» del archivo (código o nombre, sin distinguir tildes ni
 *      mayúsculas). Debe ser una carrera que el grupo atiende.
 *   2. Sin columna o con la celda vacía, la carrera elegida para todo el archivo («Todos son de»).
 *   3. La carrera con la que el estudiante ya figura en otra inscripción, si el grupo la atiende.
 *      Lo que indica el docente (1 y 2) siempre manda; esto solo completa lo que falta, y una
 *      carrera que el grupo no atiende (otra facultad, por ejemplo) no se usa.
 *   4. Si el grupo atiende una sola carrera, esa.
 *   5. Si no, la fila queda inconsistente: no se sabe de qué carrera es.
 */
class StudentRosterCareerResolver
{
    public const MISSING_CAREER = 'missing_career';

    public const CAREER_NOT_IN_GROUP = 'career_not_in_group';

    /** Origen de la carrera de la fila, para mostrarlo en la previsualización. */
    public const SOURCE_FILE = 'file';

    public const SOURCE_CHOSEN = 'chosen';

    public const SOURCE_PREVIOUS = 'previous';

    public const SOURCE_ONLY = 'only';

    private ?StudentCareerHistory $history;

    /** Sin historial (pruebas unitarias) se omite el paso 3. */
    public function __construct(?StudentCareerHistory $history = null)
    {
        $this->history = $history;
    }

    /**
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string}> $groupCareers
     */
    public function resolve(
        StudentRosterAnalysisResult $analysis,
        array $groupCareers,
        ?int $defaultCareerId
    ): StudentRosterAnalysisResult {
        $byKey = $this->careersByKey($groupCareers);
        $onlyCareerId = count($groupCareers) === 1 ? (int) $groupCareers[0]['id_carrera'] : null;
        $previous = $this->previousCareers($analysis, $groupCareers, $defaultCareerId);

        $rows = [];

        foreach ($analysis->rows() as $rowAnalysis) {
            $row = $rowAnalysis->row();
            $errors = $rowAnalysis->errors();
            $careerId = null;
            $source = null;

            if ($row->careerText() !== null) {
                $careerId = $byKey[$this->key($row->careerText())] ?? null;
                $source = self::SOURCE_FILE;

                if ($careerId === null) {
                    $errors[] = self::CAREER_NOT_IN_GROUP;
                }
            } elseif ($defaultCareerId !== null) {
                $careerId = $defaultCareerId;
                $source = self::SOURCE_CHOSEN;
            } elseif ($row->sisCode() !== null && isset($previous[$row->sisCode()])) {
                $careerId = $previous[$row->sisCode()];
                $source = self::SOURCE_PREVIOUS;
            } elseif ($onlyCareerId !== null) {
                $careerId = $onlyCareerId;
                $source = self::SOURCE_ONLY;
            } else {
                $errors[] = self::MISSING_CAREER;
            }

            $rows[] = new StudentRosterRowAnalysis($row->withCareerId($careerId, $source), $errors);
        }

        return new StudentRosterAnalysisResult($rows);
    }

    /**
     * Carreras ya registradas de los estudiantes sin carrera en el archivo, solo las que el grupo
     * atiende. No consulta nada si no hace falta: hay elección para todo el archivo, el grupo
     * atiende una sola carrera o todas las filas traen la suya.
     *
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string}> $groupCareers
     *
     * @return array<string, int>  código SIS => id_carrera
     */
    private function previousCareers(
        StudentRosterAnalysisResult $analysis,
        array $groupCareers,
        ?int $defaultCareerId
    ): array {
        if ($this->history === null || $defaultCareerId !== null || count($groupCareers) < 2) {
            return [];
        }

        $sisCodes = [];

        foreach ($analysis->rows() as $rowAnalysis) {
            $row = $rowAnalysis->row();

            if ($row->careerText() === null && $row->sisCode() !== null) {
                $sisCodes[] = $row->sisCode();
            }
        }

        if ($sisCodes === []) {
            return [];
        }

        $groupCareerIds = array_flip(array_map('intval', array_column($groupCareers, 'id_carrera')));

        return array_filter(
            $this->history->latestCareerBySis($sisCodes),
            static fn (int $careerId): bool => isset($groupCareerIds[$careerId])
        );
    }

    /**
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string}> $groupCareers
     *
     * @return array<string, int>
     */
    private function careersByKey(array $groupCareers): array
    {
        $byKey = [];

        foreach ($groupCareers as $career) {
            $byKey[$this->key($career['codigo'])] = (int) $career['id_carrera'];
            $byKey[$this->key($career['nombre'])] = (int) $career['id_carrera'];
        }

        return $byKey;
    }

    private function key(string $value): string
    {
        $normalized = mb_strtolower(AccentInsensitiveSearch::normalize($value), 'UTF-8');

        return (string) preg_replace('/\s+/u', ' ', $normalized);
    }
}
