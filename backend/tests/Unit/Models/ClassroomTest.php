<?php

namespace Tests\Unit\Models;

use App\Models\Classroom;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClassroomTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function mapea_correctamente_sus_campos()
    {
        $classroom = Classroom::create([
            'nro_aula'  => '101A',
            'capacidad' => 50,
            'ubicacion' => 'Piso 1',
            'estado'    => 'ACTIVO',
        ]);

        $this->assertDatabaseHas('ambiente', [
            'id_ambiente' => $classroom->id_ambiente,
            'nro_aula'    => '101A',
            'capacidad'   => 50,
            'ubicacion'   => 'Piso 1',
            'estado'      => 'ACTIVO',
        ]);

        $this->assertIsInt($classroom->id_ambiente);
        $this->assertIsInt($classroom->capacidad);
        $this->assertEquals(50, $classroom->capacidad);
    }

    /** @test */
    public function acepta_los_dos_estados_del_enum()
    {
        $c1 = Classroom::create([
            'nro_aula' => 'A1', 'capacidad' => 10, 'estado' => 'ACTIVO',
        ]);
        $c2 = Classroom::create([
            'nro_aula' => 'A2', 'capacidad' => 10, 'estado' => 'INACTIVO',
        ]);

        $this->assertEquals('ACTIVO', $c1->estado);
        $this->assertEquals('INACTIVO', $c2->estado);
    }

    /** @test */
    public function rechaza_capacidad_cero_o_negativa()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Classroom::create([
            'nro_aula'  => 'X1',
            'capacidad' => 0,
            'estado'    => 'ACTIVO',
        ]);
    }

    /** @test */
    public function nro_aula_es_unico()
    {
        Classroom::create([
            'nro_aula' => 'DUP', 'capacidad' => 10, 'estado' => 'ACTIVO',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Classroom::create([
            'nro_aula' => 'DUP', 'capacidad' => 20, 'estado' => 'ACTIVO',
        ]);
    }
}
