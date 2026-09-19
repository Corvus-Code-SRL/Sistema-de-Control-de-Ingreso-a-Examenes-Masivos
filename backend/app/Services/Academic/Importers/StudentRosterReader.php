<?php

namespace App\Services\Academic\Importers;

interface StudentRosterReader
{
    /**
     * @return iterable<StudentRosterRow>
     */
    public function read(string $path): iterable;
}