<?php

namespace App\Http\Requests\Academic;

/**
 * Grupos del docente para elegir dónde registrar un estudiante excepcional (HU-023).
 * Sin parámetros: solo comprueba que quien pide ejerce la función.
 */
class ListExceptionalGroupsRequest extends ExceptionalStudentRequest
{
    public function rules(): array
    {
        return [];
    }
}
