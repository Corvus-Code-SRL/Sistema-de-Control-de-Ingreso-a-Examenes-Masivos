<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo mínimo de tipos de examen: uno por cada valor de public.categoria_examen.
 *
 * Crear un examen nunca crea tipos: los toma de este catálogo según la categoría.
 */
class ExamTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            ['nombre' => 'Regular',  'categoria' => 'REGULAR'],
            ['nombre' => 'Mesa',     'categoria' => 'MESA'],
            ['nombre' => 'Admision', 'categoria' => 'ADMISION'],
        ];

        foreach ($types as $type) {
            DB::table('tipo_examen')->updateOrInsert(['nombre' => $type['nombre']], $type);
        }
    }
}
