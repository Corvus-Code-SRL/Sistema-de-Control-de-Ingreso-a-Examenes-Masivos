<?php

namespace App\Services\Academic\Importers;

use App\Models\Student;

class StudentRosterStudentCreator
{
    private StudentRosterStudentMapper $mapper;

    public function __construct(StudentRosterStudentMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    public function create(StudentRosterRow $row): Student
    {
        return Student::create(
            $this->mapper->map($row)
        );
    }
}
