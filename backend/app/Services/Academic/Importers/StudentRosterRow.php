<?php

namespace App\Services\Academic\Importers;

use App\Support\SisCode;

final class StudentRosterRow
{
    private int $rowNumber;

    private ?string $sisCode;

    private ?string $lastNames;

    private ?string $firstNames;

    /** Texto de la columna opcional «carrera» (código o nombre), tal como vino en el archivo. */
    private ?string $careerText;

    /** Carrera del grupo que se resolvió para la fila (HU-021 multicarrera); null si no se resolvió. */
    private ?int $careerId;

    public function __construct(
        int $rowNumber,
        ?string $sisCode,
        ?string $lastNames,
        ?string $firstNames,
        ?string $careerText = null,
        ?int $careerId = null
    ) {
        $this->rowNumber = $rowNumber;
        $this->sisCode = SisCode::normalizeNullable($sisCode);
        $this->lastNames = $lastNames;
        $this->firstNames = $firstNames;
        $this->careerText = $careerText;
        $this->careerId = $careerId;
    }

    public function rowNumber(): int
    {
        return $this->rowNumber;
    }

    public function sisCode(): ?string
    {
        return $this->sisCode;
    }

    public function lastNames(): ?string
    {
        return $this->lastNames;
    }

    public function firstNames(): ?string
    {
        return $this->firstNames;
    }

    public function careerText(): ?string
    {
        return $this->careerText;
    }

    public function careerId(): ?int
    {
        return $this->careerId;
    }

    /** La misma fila con la carrera ya resuelta: las filas son inmutables. */
    public function withCareerId(?int $careerId): self
    {
        return new self(
            $this->rowNumber,
            $this->sisCode,
            $this->lastNames,
            $this->firstNames,
            $this->careerText,
            $careerId
        );
    }
}
