<?php

namespace App\Services\Academic\Importers;

use App\Services\Academic\StudentRosterGroupAccess;
use LogicException;

class StudentRosterPreviewService
{
    private StudentRosterGroupAccess $groupAccess;

    private StudentRosterReaderResolver $readerResolver;

    private StudentRosterAnalyzer $analyzer;

    private StudentRosterDatabaseMatcher $databaseMatcher;

    private StudentRosterPreviewStore $previewStore;

    public function __construct(
        StudentRosterGroupAccess $groupAccess,
        StudentRosterReaderResolver $readerResolver,
        StudentRosterAnalyzer $analyzer,
        StudentRosterDatabaseMatcher $databaseMatcher,
        StudentRosterPreviewStore $previewStore
    ) {
        $this->groupAccess = $groupAccess;
        $this->readerResolver = $readerResolver;
        $this->analyzer = $analyzer;
        $this->databaseMatcher = $databaseMatcher;
        $this->previewStore = $previewStore;
    }

    public function generate(
        int $groupId,
        string $path,
        string $extension
    ): StudentRosterPreviewResult {
        $group = $this->groupAccess->getAvailable($groupId);

        $reader = $this->readerResolver->resolve($extension);

        $analysis = $this->analyzer->analyze(
            $reader->read($path)
        );

        $matches = $this->databaseMatcher->classify(
            $groupId,
            $analysis
        );

        $token = $this->previewStore->store(
            $groupId,
            (string) $group->id_usuario_docente,
            $analysis
        );

        return new StudentRosterPreviewResult(
            $token,
            $analysis->totalRows(),
            $analysis->validRows(),
            $analysis->inconsistentRows(),
            $this->buildRows($analysis, $matches)
        );
    }

    /**
     * @param array<int, StudentRosterDatabaseMatch> $matches
     *
     * @return array<int, array{
     *     row_number: int,
     *     sis_code: ?string,
     *     last_names: ?string,
     *     first_names: ?string,
     *     status: string,
     *     errors: array<int, string>
     * }>
     */
    private function buildRows(
        StudentRosterAnalysisResult $analysis,
        array $matches
    ): array {
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
                'status' => $status,
                'errors' => $rowAnalysis->errors(),
            ];
        }

        return $rows;
    }
}