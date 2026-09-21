<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\ExamType;

class ExamTypeTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function tipo_examen_mapea_correctamente()
    {
        $examType = ExamType::create([
            'nombre'    => 'Parcial',
            'categoria' => 'REGULAR'
        ]);

        $this->assertDatabaseHas('tipo_examen', [
            'id_tipo_examen' => $examType->id_tipo_examen,
            'nombre'         => 'Parcial',
            'categoria'      => 'REGULAR'
        ]);

        $this->assertEquals('Parcial', $examType->nombre);
        $this->assertEquals('REGULAR', $examType->categoria);
        $this->assertIsInt($examType->id_tipo_examen);
    }
}
