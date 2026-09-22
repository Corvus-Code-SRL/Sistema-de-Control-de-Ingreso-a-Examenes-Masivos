<?php

namespace App\Http\Requests\Exams;

use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Información general de un examen: la misma al crearlo y al modificarlo.
 *
 * Aquí se valida la forma de los datos y que existan en los catálogos. Que el par
 * materia-carrera esté activo lo decide ExamService con la regla de HU-16.
 */
abstract class ExamRequest extends FormRequest
{
    /** Valores de public.categoria_examen. */
    private const CATEGORIES = ['REGULAR', 'MESA', 'ADMISION'];

    /** Límite de las columnas integer de PostgreSQL: un id mayor rompería la consulta. */
    private const MAX_ID = 2147483647;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_examen' => ['required', 'string', 'max:25'],
            'id_carrera'    => ['bail', 'required', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'id_materia'    => [
                'bail',
                'required',
                'integer',
                'min:1',
                'max:' . self::MAX_ID,
                Rule::exists('materia_carrera', 'id_materia')->where(function ($query) {
                    $query->where('id_carrera', (int) $this->input('id_carrera'));
                }),
            ],
            'categoria'     => ['sometimes', 'required', 'string', Rule::in(self::CATEGORIES)],
            'fecha'         => ['required', 'date_format:Y-m-d'],
            'hora_inicio'   => ['required', 'date_format:H:i'],
            // Menos de un día: la hora de fin se guarda como hora del día.
            'duracion'      => ['required', 'integer', 'min:1', 'max:1439'],
            'ambientes'     => ['required', 'array', 'min:1'],
            'ambientes.*'   => [
                'bail',
                'required',
                'integer',
                'min:1',
                'max:' . self::MAX_ID,
                'distinct',
                Rule::exists('ambiente', 'id_ambiente')->where('estado', RecordStatus::ACTIVE),
            ],
            'normas'        => ['nullable', 'string', 'max:255'],
            'confirmar_advertencias' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Fecha y hora juntas no pueden quedar en el pasado. Se comparan en la hora local
     * del campus: la aplicación corre en UTC y el examen se registra en hora local.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['fecha', 'hora_inicio'])) {
                return;
            }

            $timezone = config('sciem.zona_horaria');
            $startsAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                $this->input('fecha') . ' ' . $this->input('hora_inicio'),
                $timezone
            );

            if ($startsAt->lessThanOrEqualTo(Carbon::now($timezone))) {
                $validator->errors()->add(
                    'fecha',
                    'La fecha y hora del examen no pueden ser anteriores al momento actual.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'nombre_examen.required' => 'El nombre del examen es obligatorio.',
            'nombre_examen.max'      => 'El nombre del examen no debe exceder los 25 caracteres.',
            'id_carrera.required'    => 'La materia es obligatoria. Debe seleccionarse junto con su carrera.',
            'id_carrera.*'           => 'La carrera seleccionada no es válida.',
            'id_materia.required'    => 'La materia es obligatoria.',
            'id_materia.exists'      => 'La materia seleccionada no está registrada en esa carrera.',
            'id_materia.*'           => 'La materia seleccionada no es válida.',
            'categoria.in'           => 'La categoría no es válida. Valores permitidos: REGULAR, MESA, ADMISION.',
            'fecha.required'         => 'La fecha del examen es obligatoria.',
            'fecha.date_format'      => 'La fecha del examen debe tener el formato AAAA-MM-DD.',
            'hora_inicio.required'   => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'duracion.required'      => 'La duración del examen es obligatoria.',
            'duracion.integer'       => 'La duración debe ser un número entero de minutos.',
            'duracion.min'           => 'La duración del examen debe ser de al menos 1 minuto.',
            'duracion.max'           => 'La duración del examen debe ser menor a 24 horas.',
            'ambientes.required'     => 'Debe asignar al menos un ambiente para el examen.',
            'ambientes.array'        => 'Los ambientes deben enviarse como una lista.',
            'ambientes.min'          => 'Debe asignar al menos un ambiente para el examen.',
            'ambientes.*.distinct'   => 'Un mismo ambiente no puede seleccionarse dos veces.',
            'ambientes.*.exists'     => 'Uno o más ambientes no existen en el catálogo o están inactivos.',
            'ambientes.*.*'          => 'Uno o más ambientes seleccionados no son válidos.',
            'normas.max'             => 'Las normas del examen no deben exceder los 255 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_examen' => 'nombre del examen',
            'id_carrera'    => 'carrera',
            'id_materia'    => 'materia',
            'categoria'     => 'categoría',
            'hora_inicio'   => 'hora de inicio',
            'duracion'      => 'duración',
            'ambientes.*'   => 'ambiente',
            'confirmar_advertencias' => 'confirmación de advertencias',
        ];
    }
}
