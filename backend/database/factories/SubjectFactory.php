<?php

namespace Database\Factories;

use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubjectFactory extends Factory
{
    protected $model = \App\Models\Subject::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Materia ' . Str::random(5),
            'codigo' => 'MAT-' . Str::upper(Str::random(6)),
            'descripcion' => null,
            'estado' => RecordStatus::ACTIVE,
        ];
    }
}