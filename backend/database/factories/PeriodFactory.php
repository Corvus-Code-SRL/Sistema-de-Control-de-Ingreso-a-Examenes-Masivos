<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PeriodFactory extends Factory
{
    protected $model = \App\Models\Period::class;

    public function definition(): array
    {
        return [
            'nombre_periodo' => 'P' . Str::upper(Str::random(3)),
            'gestion' => 2026,
        ];
    }
}