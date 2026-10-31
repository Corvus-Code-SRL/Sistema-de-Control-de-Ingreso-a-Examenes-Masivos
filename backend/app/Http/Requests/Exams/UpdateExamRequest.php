<?php

namespace App\Http\Requests\Exams;

/**
 * Modificación de la información general de un examen (HU-24, criterios 10 y 11).
 *
 * Valida lo mismo que la creación; si el examen todavía admite cambios lo decide
 * ExamService según su estado.
 */
class UpdateExamRequest extends ExamRequest
{
}
