<?php

namespace App\Services\Exams\Importers;

use App\Services\Academic\Importers\StudentRosterRow;

/**
 * Preview en caché de la lista de habilitados de un examen (HU-028).
 *
 * A diferencia de StudentRosterPreview (HU-021), está atado a un examen,
 * no a un grupo. El resto del shape es el mismo: filas ya leídas del
 * archivo, sin datos de base de datos.
 */
final class EligibleListPreview
{
    private int $examId;

    private string $teacherId;

    /** @var array<int, StudentRosterRow> */
    private array $rows;

    /**
     * @param array<int, StudentRosterRow> $rows
     */
    public function __construct(
        int $examId,
        string $teacherId,
        array $rows
    ) {
        $this->examId = $examId;
        $this->teacherId = $teacherId;
        $this->rows = $rows;
    }

    public function examId(): int
    {
        return $this->examId;
    }

    public function teacherId(): string
    {
        return $this->teacherId;
    }

    /**
     * @return array<int, StudentRosterRow>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
