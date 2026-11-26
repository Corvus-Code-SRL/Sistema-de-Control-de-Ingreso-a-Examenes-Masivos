<?php

namespace App\Services\Exams;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Builder;

/** Ventana compartida para preparar habilitados y ambientes antes de abrir el control. */
class ExamPreparationWindow
{
    public const CLOSED_MESSAGE = 'El examen ya no permite cambios de preparación.';

    public function isOpen(Exam $exam): bool
    {
        return $exam->estado === Exam::PROGRAMADO && $exam->ingreso_abierto_en === null;
    }

    /**
     * @param Builder<Exam> $query
     * @return Builder<Exam>
     */
    public function applyOpen(Builder $query): Builder
    {
        return $query->where('estado', Exam::PROGRAMADO)->whereNull('ingreso_abierto_en');
    }
}
