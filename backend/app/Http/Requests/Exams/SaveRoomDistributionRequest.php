<?php

namespace App\Http\Requests\Exams;

use App\Support\Exams\DistributionMessages;

class SaveRoomDistributionRequest extends ShowRoomDistributionRequest
{
    private const MAX_ID = 2147483647;
    private const VERSION_PATTERN = '/\A[a-f0-9]{40}\z/';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'version' => ['bail', 'required', 'string', 'regex:' . self::VERSION_PATTERN],
            'asignaciones' => ['present', 'array'],
            'asignaciones.*.id_estudiante' => [
                'bail', 'required', 'integer', 'min:1', 'max:' . self::MAX_ID, 'distinct',
            ],
            'asignaciones.*.id_ambiente' => ['bail', 'required', 'integer', 'min:1', 'max:' . self::MAX_ID],
        ];
    }

    /** @return list<array{id_estudiante:int, id_ambiente:int}> */
    public function assignments(): array
    {
        return array_values(array_map(fn (array $assignment): array => [
            'id_estudiante' => (int) $assignment['id_estudiante'],
            'id_ambiente' => (int) $assignment['id_ambiente'],
        ], $this->validated()['asignaciones']));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'version.required' => DistributionMessages::INVALID_VERSION,
            'version.string' => DistributionMessages::INVALID_VERSION,
            'version.regex' => DistributionMessages::INVALID_VERSION,
            'asignaciones.present' => DistributionMessages::ASSIGNMENTS_REQUIRED,
            'asignaciones.array' => DistributionMessages::INVALID_ASSIGNMENTS,
            'asignaciones.*.id_estudiante.required' => DistributionMessages::INVALID_STUDENT,
            'asignaciones.*.id_estudiante.integer' => DistributionMessages::INVALID_STUDENT,
            'asignaciones.*.id_estudiante.min' => DistributionMessages::INVALID_STUDENT,
            'asignaciones.*.id_estudiante.max' => DistributionMessages::INVALID_STUDENT,
            'asignaciones.*.id_estudiante.distinct' => DistributionMessages::DUPLICATE_STUDENT,
            'asignaciones.*.id_ambiente.required' => DistributionMessages::INVALID_ROOM,
            'asignaciones.*.id_ambiente.integer' => DistributionMessages::INVALID_ROOM,
            'asignaciones.*.id_ambiente.min' => DistributionMessages::INVALID_ROOM,
            'asignaciones.*.id_ambiente.max' => DistributionMessages::INVALID_ROOM,
        ];
    }
}
