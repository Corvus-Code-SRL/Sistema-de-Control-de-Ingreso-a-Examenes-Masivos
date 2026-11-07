<?php

namespace App\Services\Academic\Importers;

use App\Services\Academic\GroupCareerService;
use App\Services\Academic\StudentRosterGroupAccess;
use App\Exceptions\Academic\StudentRosterFileException;
use Illuminate\Validation\ValidationException;
use LogicException;

class StudentRosterPreviewService
{
    private StudentRosterGroupAccess $groupAccess;

    private StudentRosterReaderResolver $readerResolver;

    private StudentRosterAnalyzer $analyzer;

    private StudentRosterDatabaseMatcher $databaseMatcher;

    private StudentRosterPreviewStore $previewStore;

    private GroupCareerService $groupCareers;

    private StudentRosterCareerResolver $careerResolver;

    public function __construct(
        StudentRosterGroupAccess $groupAccess,
        StudentRosterReaderResolver $readerResolver,
        StudentRosterAnalyzer $analyzer,
        StudentRosterDatabaseMatcher $databaseMatcher,
        StudentRosterPreviewStore $previewStore,
        GroupCareerService $groupCareers,
        StudentRosterCareerResolver $careerResolver
    ) {
        $this->groupAccess = $groupAccess;
        $this->readerResolver = $readerResolver;
        $this->analyzer = $analyzer;
        $this->databaseMatcher = $databaseMatcher;
        $this->previewStore = $previewStore;
        $this->groupCareers = $groupCareers;
        $this->careerResolver = $careerResolver;
    }

    /**
     * `$defaultCareerId` es la carrera elegida para las filas que no traen la columna «carrera»
     * (HU-021 multicarrera); debe ser una carrera que el grupo atiende.
     */
    public function generate(
        int $groupId,
        string $path,
        string $extension,
        string $teacherId,
        ?int $defaultCareerId = null
    ): StudentRosterPreviewResult {
        $group = $this->groupAccess->getAvailable($groupId, $teacherId);

        $careers = $this->groupCareers->careersOf($group);

        $this->assertCareerBelongsToGroup($defaultCareerId, $careers);

        $reader = $this->readerResolver->resolve($extension);

        $analysis = $this->careerResolver->resolve(
            $this->analyzer->analyze($reader->read($path)),
            $careers,
            $defaultCareerId
        );

        if ($analysis->totalRows() === 0) {
            throw new StudentRosterFileException(
                'La nómina no contiene estudiantes.'
            );
        }

        $matches = $this->databaseMatcher->classify(
            $groupId,
            $analysis
        );

        $token = $this->previewStore->store(
            $groupId,
            $teacherId,
            $analysis,
            $defaultCareerId
        );

        return new StudentRosterPreviewResult(
            $token,
            $analysis->totalRows(),
            $analysis->validRows(),
            $analysis->inconsistentRows(),
            $this->buildRows($analysis, $matches, $careers),
            $careers
        );
    }

    /**
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}> $careers
     *
     * @throws ValidationException
     */
    private function assertCareerBelongsToGroup(?int $careerId, array $careers): void
    {
        if ($careerId === null || in_array($careerId, array_column($careers, 'id_carrera'), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'id_carrera' => ['La carrera elegida no corresponde a este grupo.'],
        ]);
    }

    /**
     * @param array<int, StudentRosterDatabaseMatch> $matches
     * @param array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}> $careers
     *
     * @return array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     career_text: ?string,
     *     career: ?array{id_carrera: int, nombre: string},
     *     status: string,
     *     errors: array<int, string>
     * }>
     */
    private function buildRows(
        StudentRosterAnalysisResult $analysis,
        array $matches,
        array $careers
    ): array {
        $careerNames = array_column($careers, 'nombre', 'id_carrera');

        $matchesByRowNumber = [];

        foreach ($matches as $match) {
            $rowNumber = $match
                ->rowAnalysis()
                ->row()
                ->rowNumber();

            $matchesByRowNumber[$rowNumber] = $match;
        }

        $rows = [];

        foreach ($analysis->rows() as $rowAnalysis) {
            $row = $rowAnalysis->row();

            if (!$rowAnalysis->isValid()) {
                $status = StudentRosterPreviewResult::INCONSISTENT;
            } else {
                $match = $matchesByRowNumber[
                    $row->rowNumber()
                ] ?? null;

                if ($match === null) {
                    throw new LogicException(
                        'Una fila válida no pudo clasificarse contra la base de datos.'
                    );
                }

                $status = $match->status();
            }

            $rows[] = [
                'row_number' => $row->rowNumber(),
                'sis_code' => $row->sisCode(),
                'last_names' => $row->lastNames(),
                'first_names' => $row->firstNames(),
                'career_text' => $row->careerText(),
                'career' => $row->careerId() === null ? null : [
                    'id_carrera' => $row->careerId(),
                    'nombre' => (string) ($careerNames[$row->careerId()] ?? ''),
                ],
                'status' => $status,
                'errors' => $rowAnalysis->errors(),
            ];
        }

        return $rows;
    }
}
