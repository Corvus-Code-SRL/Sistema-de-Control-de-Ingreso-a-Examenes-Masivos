<?php

namespace Tests\Unit\Services\Exams;

use App\Services\Exams\ClassroomService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassroomServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const ACTOR_ID = '00000000-0000-4000-8000-0000000000aa';

    /**
     * Simula la condición de carrera: nada aguas arriba detectó el duplicado (se
     * inserta directo en la base, sin pasar por el Form Request) y es la propia
     * constraint UNIQUE(nro_aula) la que la detiene.
     *
     * @test
     */
    public function traduce_una_violacion_de_unicidad_a_validation_exception()
    {
        DB::table('ambiente')->insert([
            'nro_aula' => 'Aula 101',
            'capacidad' => 20,
            'ubicacion' => 'Modulo B',
            'estado' => 'ACTIVO',
        ]);

        $this->expectException(ValidationException::class);

        app(ClassroomService::class)->registrar([
            'nro_aula' => 'Aula 101',
            'capacidad' => 30,
            'ubicacion' => 'Modulo A',
        ], self::ACTOR_ID);
    }

    /** @test */
    public function traduce_una_violacion_de_capacidad_a_validation_exception()
    {
        $this->expectException(ValidationException::class);

        app(ClassroomService::class)->registrar([
            'nro_aula' => 'Aula 202',
            'capacidad' => 0,
            'ubicacion' => 'Modulo A',
        ], self::ACTOR_ID);
    }
}
