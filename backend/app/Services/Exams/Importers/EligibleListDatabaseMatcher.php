<?php

namespace App\Services\Exams\Importers;

use App\Services\Exams\ExamRosterService;
use App\Services\Academic\Importers\StudentRosterAnalysisResult;
use App\Services\Academic\Importers\StudentRosterRowAnalysis;
use App\Support\SisCode;
use Illuminate\Support\Facades\DB;

/**
 * Cruza los cod_sis válidos del archivo contra examen_estudiante del
 * examen (HU-028).
 *
 * MATCHED: el cod_sis tiene fila en examen_estudiante para este examen,
 * venga de la nómina del grupo o de HU-023 (excepcional).
 * NOT_FOUND_IN_EXAM: el cod_sis no tiene fila en examen_estudiante para
 * este examen. Es un aviso, no un bloqueo: se ignora al confirmar.
 *
 * Las filas INCONSISTENT o DUPLICATE no llegan aquí: no son validRows.
 */
class EligibleListDatabaseMatcher
{
    private ExamRosterService $roster;

    public function __construct(ExamRosterService $roster)
    {
        $this->roster = $roster;
    }

    /**
     * @return array<int, EligibleListDatabaseMatch>
     */
    public function classify(
        int $examId,
        StudentRosterAnalysisResult $analysis
    ): array {
        $validRows = $this->validRows($analysis);

        if ($validRows === []) {
            return [];
        }

        $sisCodes = $this->sisCodes($validRows);

        $matches = $this->roster->expected($examId)
            ->whereIn('estudiante.cod_sis', $sisCodes)
            ->get([
                'estudiante.id_estudiante',
                'estudiante.cod_sis',
                'estudiante.nombre',
                'estudiante.apellido_paterno',
                'estudiante.apellido_materno',
                'id_grupo',
                'num_grupo',
            ]);

        $bySis = [];

        foreach ($matches as $row) {
            $bySis['sis:' . SisCode::normalize((string) $row->cod_sis)] = $row;
        }

        $result = [];

        foreach ($validRows as $rowAnalysis) {
            $sisCode = $rowAnalysis->row()->sisCode();

            if ($sisCode === null) {
                continue;
            }

            $row = $bySis['sis:' . $sisCode] ?? null;

            if ($row === null) {
                $result[] = new EligibleListDatabaseMatch(
                    $rowAnalysis,
                    null,
                    null,
                    null,
                    null,
                    EligibleListDatabaseMatch::NOT_FOUND_IN_EXAM
                );

                continue;
            }

            $result[] = new EligibleListDatabaseMatch(
                $rowAnalysis,
                (int) $row->id_estudiante,
                $this->fullName($row),
                (int) $row->id_grupo,
                'Grupo ' . $row->num_grupo,
                EligibleListDatabaseMatch::MATCHED
            );
        }

        return $result;
    }

    /**
     * Cuenta los estudiantes de la nómina del examen cuyo cod_sis NO
     * está en el archivo. Son los que quedarán NO_HABILITADO al confirmar.
     *
     * @param array<int, string> $sisCodesInFile
     */
    public function countMissing(int $examId, array $sisCodesInFile): int
    {
        $normalized = array_values(array_unique(array_map(
            static fn (string $code): string => SisCode::normalize($code),
            $sisCodesInFile
        )));

        $query = $this->roster->expected($examId);

        if ($normalized !== []) {
            $query->whereNotIn('estudiante.cod_sis', $normalized);
        }

        return (int) $query->count();
    }

    /**
     * @return array<int, StudentRosterRowAnalysis>
     */
    private function validRows(StudentRosterAnalysisResult $analysis): array
    {
        return array_values(array_filter(
            $analysis->rows(),
            static function (StudentRosterRowAnalysis $row): bool {
                return $row->isValid();
            }
        ));
    }

    /**
     * @param array<int, StudentRosterRowAnalysis> $rows
     * @return array<int, string>
     */
    private function sisCodes(array $rows): array
    {
        $sisCodes = [];

        foreach ($rows as $rowAnalysis) {
            $sisCode = $rowAnalysis->row()->sisCode();

            if ($sisCode !== null) {
                $sisCodes[] = $sisCode;
            }
        }

        return array_values(array_unique($sisCodes));
    }

    private function fullName(object $row): string
    {
        $parts = array_filter([
            (string) $row->apellido_paterno,
            $row->apellido_materno !== null ? (string) $row->apellido_materno : null,
            (string) $row->nombre,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return implode(' ', $parts);
    }
}
