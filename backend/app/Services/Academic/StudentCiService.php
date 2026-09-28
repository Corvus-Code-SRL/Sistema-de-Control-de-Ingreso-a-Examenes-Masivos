<?php

namespace App\Services\Academic;

use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Captura el CI de un estudiante creado desde una nómina.
 *
 * Los estudiantes de nómina nacen con ci = NULL. Este es el punto de extensión para
 * EX04 (registro de ingreso): en la primera verificación, cuando el estudiante
 * presenta su documento, el control de ingreso llama a captureCi() y el CI queda
 * guardado. estudiante.ci es UNIQUE, y PostgreSQL admite varios NULL.
 */
class StudentCiService
{
    private const MAX_CI_LENGTH = 10;

    public function captureCi(Student $student, string $ci): Student
    {
        $ci = trim($ci);

        if ($ci === '' || mb_strlen($ci) > self::MAX_CI_LENGTH) {
            throw ValidationException::withMessages([
                'ci' => ['El CI debe tener entre 1 y ' . self::MAX_CI_LENGTH . ' caracteres.'],
            ]);
        }

        if ($student->ci === $ci) {
            return $student;
        }

        if ($student->ci !== null) {
            throw ValidationException::withMessages([
                'ci' => ['El estudiante ya tiene un CI registrado distinto.'],
            ]);
        }

        try {
            DB::transaction(function () use ($student, $ci): void {
                $student->ci = $ci;
                $student->save();
            });
        } catch (QueryException $exception) {
            $student->ci = null;

            // 23505 es el SQLSTATE de "unique_violation" en PostgreSQL.
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages([
                    'ci' => ['Ese CI ya pertenece a otro estudiante.'],
                ]);
            }

            throw $exception;
        }

        return $student;
    }
}
