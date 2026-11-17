<?php

namespace App\Http\Requests\Academic;

class ExceptionalOptionsRequest extends ExceptionalStudentRequest
{
    public function rules(): array
    {
        return ['id_examen' => ['required', 'integer', 'min:1', 'max:' . self::MAX_ID]];
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()->parameters());
    }

    public function examId(): int
    {
        return (int) $this->validated()['id_examen'];
    }
}
