<?php

namespace App\Http\Requests\Incidents;

class ExamSituationContextRequest extends ListExamSituationsRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'q' => ['sometimes', 'nullable', 'string', 'max:50'],
            'id_estudiante' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999'],
        ]);
    }
}