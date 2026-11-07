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
 *   3. Si el grupo atiende una sola carrera, esa.
 *   4. Si no, la fila queda inconsistente: no se sabe de qué carrera es.
 */
class StudentRosterCareerResolver
{
    public const MISSING_CAREER = 'missing_career';

    public const CAREER_NOT_IN_GROUP = 'career_not_in_group';

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
        $fallbackId = $defaultCareerId ?? $onlyCareerId;

        $rows = [];

        foreach ($analysis->rows() as $rowAnalysis) {
            $row = $rowAnalysis->row();
            $errors = $rowAnalysis->errors();
            $careerId = null;

            if ($row->careerText() !== null) {
                $careerId = $byKey[$this->key($row->careerText())] ?? null;

                if ($careerId === null) {
                    $errors[] = self::CAREER_NOT_IN_GROUP;
                }
            } elseif ($fallbackId !== null) {
                $careerId = $fallbackId;
            } else {
                $errors[] = self::MISSING_CAREER;
            }

            $rows[] = new StudentRosterRowAnalysis($row->withCareerId($careerId), $errors);
        }

        return new StudentRosterAnalysisResult($rows);
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
