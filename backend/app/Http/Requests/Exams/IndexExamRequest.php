<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Listado de exámenes del docente. `vista=programados` pide solo los que siguen vigentes:
 * PROGRAMADO o EN_INGRESO, del período activo, del más próximo al más lejano.
 */
class IndexExamRequest extends FormRequest
{
    public const VIEW_SCHEDULED = 'programados';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vista' => ['nullable', 'string', 'in:' . self::VIEW_SCHEDULED],
        ];
    }

    public function messages(): array
    {
        return [
            'vista.in' => 'La vista solicitada no existe. Use «programados» o no envíe el parámetro.',
        ];
    }

    public function wantsScheduledOnly(): bool
    {
        return ($this->validated()['vista'] ?? null) === self::VIEW_SCHEDULED;
    }
}
