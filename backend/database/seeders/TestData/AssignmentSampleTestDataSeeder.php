<?php

namespace Database\Seeders\TestData;

use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de muestra para verificar a mano la asignación por período (HU-006).
 *
 * Garantiza la gestión 2026 con los períodos 1/2026 y 2/2026 y agrega:
 *  - una electiva de Ingeniería de Sistemas ofertada únicamente en 1/2026 (historial de consulta):
 *    en 2/2026, el período vigente, es asignable a Sistemas reutilizando el par existente;
 *  - Ingeniería Industrial, una carrera activa sin pares ni ofertas en ningún período: en 2/2026
 *    muestra la lista vacía y cualquier materia activa (p. ej. Cálculo I) es asignable creando el par.
 *
 * Es idempotente y no cambia qué período es el vigente: solo lo fija en 2/2026 si ninguno lo era.
 * Debe correr después de CatalogTestDataSeeder (necesita la carrera) y no forma parte de
 * DatabaseSeeder.
 */
class AssignmentSampleTestDataSeeder extends Seeder
{
    public function run()
    {
        LocalDatabaseGuard::assertLocal(static::class);

        $yearId = $this->ensureYear2026();
        $firstSemesterId = $this->ensurePeriod($yearId, 1, 'Semestre 1', Ids::PERIOD_2026_1);
        $secondSemesterId = $this->ensurePeriod($yearId, 2, 'Semestre 2', Ids::PERIOD_ACTIVE);

        if (! DB::table('gestion_periodo')->where('vigente', true)->exists()) {
            DB::table('gestion_periodo')
                ->where('id_gestion_periodo', $secondSemesterId)
                ->update(['vigente' => true]);
        }

        DB::table('carrera')->insertOrIgnore([
            'id_carrera' => Ids::CAREER_NO_OFFERINGS_SAMPLE,
            'nombre' => 'Ingeniería Industrial',
            'codigo' => 'IND',
            'descripcion' => null,
            'estado' => 'ACTIVO',
            'id_facultad' => Ids::FACULTY_SCIENCE,
        ]);

        DB::table('materia')->insertOrIgnore([
            'id_materia' => Ids::SUBJECT_ELECTIVE_SAMPLE,
            'nombre' => 'Seminario de Investigación',
            'codigo' => 'SIS-401',
            'descripcion' => null,
            'estado' => 'ACTIVO',
        ]);

        DB::table('materia_carrera')->insertOrIgnore([
            'id_carrera' => Ids::CAREER_SYSTEMS,
            'id_materia' => Ids::SUBJECT_ELECTIVE_SAMPLE,
            'nivel_semestre' => '7',
            'obligatoria' => false,
            'estado' => 'ACTIVO',
        ]);

        DB::table('oferta_academica')->insertOrIgnore([
            'id_carrera' => Ids::CAREER_SYSTEMS,
            'id_materia' => Ids::SUBJECT_ELECTIVE_SAMPLE,
            'id_gestion_periodo' => $firstSemesterId,
            'estado' => 'ACTIVO',
        ]);
    }

    private function ensureYear2026(): int
    {
        DB::table('gestion')->insertOrIgnore([
            'id_gestion' => Ids::YEAR_2026,
            'anio' => 2026,
            'estado' => 'ACTIVO',
        ]);

        return (int) DB::table('gestion')->where('anio', 2026)->value('id_gestion');
    }

    /** La instancia (gestión, tipo de período), con el id fijo solo si todavía no existe. */
    private function ensurePeriod(int $yearId, int $code, string $typeName, int $fixedId): int
    {
        DB::table('periodo')->insertOrIgnore(['codigo' => $code, 'nombre' => $typeName]);

        $typeId = (int) DB::table('periodo')->where('codigo', $code)->value('id_periodo');

        DB::table('gestion_periodo')->insertOrIgnore([
            'id_gestion_periodo' => $fixedId,
            'id_gestion' => $yearId,
            'id_periodo' => $typeId,
            'vigente' => false,
            'estado' => 'ACTIVO',
        ]);

        return (int) DB::table('gestion_periodo')
            ->where('id_gestion', $yearId)
            ->where('id_periodo', $typeId)
            ->value('id_gestion_periodo');
    }
}
