<?php

namespace App\Services\Academic\Importers;

use App\Models\Student;
use RuntimeException;

class StudentRosterStudentCreator
{
    private const MAX_CI_ATTEMPTS = 10;

    private TemporaryStudentCiGenerator $ciGenerator;

    private StudentRosterStudentMapper $mapper;

    public function __construct(
        TemporaryStudentCiGenerator $ciGenerator,
        StudentRosterStudentMapper $mapper
    ) {
        $this->ciGenerator = $ciGenerator;
        $this->mapper = $mapper;
    }

    public function create(StudentRosterRow $row): Student
    {
        $temporaryCi = $this->generateAvailableCi();

        return Student::create(
            $this->mapper->map($row, $temporaryCi)
        );
    }

    private function generateAvailableCi(): string
    {
        for (
            $attempt = 0;
            $attempt < self::MAX_CI_ATTEMPTS;
            $attempt++
        ) {
            $candidate = $this->ciGenerator->generate();

            $exists = Student::query()
                ->where('ci', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'No se pudo generar un CI temporal disponible.'
        );
    }
}