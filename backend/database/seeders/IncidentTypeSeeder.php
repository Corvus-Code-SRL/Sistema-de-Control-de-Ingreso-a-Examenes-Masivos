<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de tipos de falta (public.tipo_falta) que usarán los reportes de incidentes.
 *
 * Aditivo e idempotente: cada tipo se busca por `nombre` y solo se inserta si falta. Nunca
 * actualiza ni borra, así que es seguro sobre la base compartida: una fila que ya existía con
 * otra descripción se deja como está. Se carga a mano:
 *
 *     php artisan db:seed --class="Database\Seeders\IncidentTypeSeeder"
 */
class IncidentTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            ['nombre' => 'Copia en examen', 'descripcion' => 'Uso de apuntes, comunicación con otros estudiantes o copia de respuestas.'],
            ['nombre' => 'Suplantación de identidad', 'descripcion' => 'Rendir el examen en nombre de otra persona.'],
            ['nombre' => 'Material no permitido', 'descripcion' => 'Posesión o uso de material, dispositivos o ayudas no autorizados.'],
            ['nombre' => 'Conducta indebida', 'descripcion' => 'Comportamiento que altera el orden o desacata las indicaciones del examen.'],
        ];

        foreach ($types as $type) {
            if (! DB::table('tipo_falta')->where('nombre', $type['nombre'])->exists()) {
                DB::table('tipo_falta')->insert($type);
            }
        }
    }
}
