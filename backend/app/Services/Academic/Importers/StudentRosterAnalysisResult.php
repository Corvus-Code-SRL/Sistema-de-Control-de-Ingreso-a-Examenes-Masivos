<?php

namespace App\Services\Academic\Importers;

final class StudentRosterAnalysisResult
{
    /**
     * @var array<int, StudentRosterRowAnalysis>
     */
    private array $rows;

    /**
     * @param array<int, StudentRosterRowAnalysis> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * @return array<int, StudentRosterRowAnalysis>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function totalRows(): int
    {
        return count($this->rows);
    }

    public function validRows(): int
    {
        return count(array_filter(
            $this->rows,
            static function (StudentRosterRowAnalysis $row): bool {
                return $row->isValid();
            }
        ));
    }

    public function inconsistentRows(): int
    {
        return $this->totalRows() - $this->validRows();
    }
}