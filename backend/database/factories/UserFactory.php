<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id_usuario'       => (string) Str::uuid(),
            'nombre'           => $this->faker->firstName(),
            'apellido_paterno' => $this->faker->lastName(),
            'apellido_materno' => $this->faker->lastName(),
            'correo'           => $this->faker->unique()->safeEmail(),
            'contrasenia'      => Hash::make('password'),
            'cod_sis'          => (string) $this->faker->unique()->numberBetween(200000000, 209999999),
            'estado'           => User::ESTADO_ACTIVO,
        ];
    }

    /** Estado alternativo: cuenta deshabilitada (lo usa la HU-004). */
    public function inactivo(): self
    {
        return $this->state(fn () => ['estado' => User::ESTADO_INACTIVO]);
    }
}