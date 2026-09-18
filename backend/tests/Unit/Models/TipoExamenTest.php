<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\TipoExamen;

class TipoExamenTest extends TestCase{

    use DatabaseTransactions;

    /** @test */
    public function tipo_examen_mapea_correctamente(){
        $tipoExamen = TipoExamen::create([
            'nombre'=> "Parcial",
            'categoria'=> "REGULAR"
        ]);

        $this->assertDatabaseHas('tipo_examen',[
            'id_tipo_examen' => $tipoExamen->id_tipo_examen,
            'nombre'=>"Parcial",
            'categoria'=>"REGULAR"
        ]);

        $this->assertEquals('Parcial', $tipoExamen->nombre);
        $this->assertEquals('REGULAR', $tipoExamen->categoria);
        $this->assertIsInt($tipoExamen->id_tipo_examen);
    }
}