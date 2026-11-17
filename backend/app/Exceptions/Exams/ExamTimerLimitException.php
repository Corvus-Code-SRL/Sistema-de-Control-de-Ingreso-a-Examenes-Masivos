<?php

namespace App\Exceptions\Exams;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Mantiene el mismo mensaje de límite en la respuesta y en el campo de minutos. */
class ExamTimerLimitException extends ValidationException
{
    public function __construct()
    {
        $message = 'El tiempo solicitado excede el límite disponible';
        $validator = Validator::make([], []);
        $validator->errors()->add('minutos', $message);

        parent::__construct($validator);

        $this->message = $message;
    }
}
