<?php

namespace Database\Factories;

use App\Models\Facultad;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CareerFactory extends Factory
{
    protected $model = \App\Models\Career::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Carrera ' . Str::random(5),
            'codigo' => 'CAR-' . Str::upper(Str::random(6)),
            'descripcion' => null,
            'estado' => RecordStatus::ACTIVE,
            'id_facultad' => Facultad::factory(),
        ];
    }
}