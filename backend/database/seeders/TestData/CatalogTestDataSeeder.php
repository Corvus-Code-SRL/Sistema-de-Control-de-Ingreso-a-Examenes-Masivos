<?php

namespace Database\Seeders\TestData;

use Database\Seeders\Support\LocalDatabaseGuard;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo académico de prueba: facultades, carreras, materias, pares materia-carrera,
 * gestiones, periodos con su oferta académica, ambientes y tipos de examen.
 */
class CatalogTestDataSeeder extends Seeder
{
    public function run()
    {
        LocalDatabaseGuard::assertLocal(static::class);

        DB::table('facultad')->upsert([
            $this->faculty(Ids::FACULTY_SCIENCE, 'Facultad de Ciencias y Tecnología', 'FCYT'),
            $this->faculty(Ids::FACULTY_ECONOMICS, 'Facultad de Ciencias Económicas', 'FCE'),
        ], ['id_facultad']);

        DB::table('carrera')->upsert([
            $this->career(Ids::CAREER_SYSTEMS, 'Ingeniería de Sistemas', 'SIS', Ids::FACULTY_SCIENCE),
            $this->career(Ids::CAREER_INFORMATICS, 'Ingeniería Informática', 'INF', Ids::FACULTY_SCIENCE),
            $this->career(Ids::CAREER_ECONOMICS, 'Economía', 'ECO', Ids::FACULTY_ECONOMICS),
            $this->career(Ids::CAREER_BUSINESS, 'Administración de Empresas', 'ADM', Ids::FACULTY_ECONOMICS),
        ], ['id_carrera']);

        DB::table('materia')->upsert([
            $this->subject(Ids::SUBJECT_CALCULUS, 'Cálculo I', 'MAT-101'),
            $this->subject(Ids::SUBJECT_ALGEBRA, 'Álgebra Lineal', 'MAT-103'),
            $this->subject(Ids::SUBJECT_PROGRAMMING, 'Introducción a la Programación', 'SIS-101'),
            $this->subject(Ids::SUBJECT_DATABASES, 'Base de Datos I', 'SIS-201'),
            $this->subject(Ids::SUBJECT_STATISTICS, 'Estadística I', 'EST-201'),
            $this->subject(Ids::SUBJECT_MICROECONOMICS, 'Microeconomía', 'ECO-201'),
            $this->subject(Ids::SUBJECT_ACCOUNTING, 'Contabilidad General', 'ADM-101'),
            // Materia INACTIVA con un par ACTIVO: el par sigue sin poder usarse.
            $this->subject(Ids::SUBJECT_ARCHIVED, 'Taller de Sistemas Operativos', 'SIS-310', 'INACTIVO'),
        ], ['id_materia']);

        DB::table('materia_carrera')->upsert([
            // Cálculo I se dicta en carreras de las dos facultades.
            $this->pair(Ids::CAREER_SYSTEMS, Ids::SUBJECT_CALCULUS, '1', true),
            $this->pair(Ids::CAREER_INFORMATICS, Ids::SUBJECT_CALCULUS, '1', true),
            $this->pair(Ids::CAREER_ECONOMICS, Ids::SUBJECT_CALCULUS, '1', true),
            $this->pair(Ids::CAREER_SYSTEMS, Ids::SUBJECT_ALGEBRA, '2', true),
            $this->pair(Ids::CAREER_INFORMATICS, Ids::SUBJECT_ALGEBRA, '2', true),
            $this->pair(Ids::CAREER_SYSTEMS, Ids::SUBJECT_PROGRAMMING, '1', true),
            $this->pair(Ids::CAREER_INFORMATICS, Ids::SUBJECT_PROGRAMMING, '1', true),
            $this->pair(Ids::CAREER_SYSTEMS, Ids::SUBJECT_DATABASES, '5', true),
            // Materia ACTIVA con el par INACTIVO en Informática.
            $this->pair(Ids::CAREER_INFORMATICS, Ids::SUBJECT_DATABASES, '5', true, 'INACTIVO'),
            $this->pair(Ids::CAREER_SYSTEMS, Ids::SUBJECT_STATISTICS, '4', false),
            $this->pair(Ids::CAREER_ECONOMICS, Ids::SUBJECT_STATISTICS, '3', true),
            $this->pair(Ids::CAREER_BUSINESS, Ids::SUBJECT_STATISTICS, '3', true),
            $this->pair(Ids::CAREER_ECONOMICS, Ids::SUBJECT_MICROECONOMICS, '3', true),
            $this->pair(Ids::CAREER_BUSINESS, Ids::SUBJECT_ACCOUNTING, '1', true),
            $this->pair(Ids::CAREER_SYSTEMS, Ids::SUBJECT_ARCHIVED, '6', false),
        ], ['id_carrera', 'id_materia']);

        $this->seedPeriods();

        DB::table('ambiente')->upsert([
            $this->classroom(9601, '691A', 60, 'Edificio nuevo, planta baja'),
            $this->classroom(9602, '691B', 60, 'Edificio nuevo, planta baja'),
            $this->classroom(9603, '692C', 45, 'Edificio nuevo, primer piso'),
            $this->classroom(9604, 'LAB-1', 30, 'Laboratorio de cómputo'),
            $this->classroom(9605, 'AUD-1', 200, 'Auditorio central'),
            // Ambiente fuera de servicio: no debe ofrecerse al programar un examen.
            $this->classroom(9606, '612', 40, 'Edificio antiguo, en refacción', 'INACTIVO'),
        ], ['id_ambiente']);

        /*
         * ExamService toma, por categoría, el tipo de menor id. Estos van en el rango 9700
         * para que REGULAR siga resolviéndose a «Regular» (ExamTypeSeeder) y no a «Parcial».
         * categoria_examen no tiene PARCIAL ni FINAL: se modelan como nombres de REGULAR.
         */
        DB::table('tipo_examen')->upsert([
            ['id_tipo_examen' => 9701, 'nombre' => 'Parcial', 'categoria' => 'REGULAR'],
            ['id_tipo_examen' => 9702, 'nombre' => 'Final', 'categoria' => 'REGULAR'],
        ], ['id_tipo_examen']);
    }

    /**
     * Gestiones 2025 y 2026, el catálogo de períodos y las instancias 2/2025, 1/2026 y 2/2026 (la
     * vigente, id 9303). Cada par ACTIVO de los datos de prueba se oferta en el período activo; las
     * ofertas de los grupos de otros períodos las agrega GroupTestDataSeeder.
     */
    private function seedPeriods(): void
    {
        DB::table('gestion')->upsert([
            ['id_gestion' => Ids::YEAR_2025, 'anio' => 2025, 'estado' => 'ACTIVO'],
            ['id_gestion' => Ids::YEAR_2026, 'anio' => 2026, 'estado' => 'ACTIVO'],
        ], ['id_gestion'], ['anio', 'estado']);

        // La migración ya los crea; se repiten por si la base se cargó desde otro camino.
        DB::table('periodo')->upsert([
            ['codigo' => 0, 'nombre' => 'Anual'],
            ['codigo' => 1, 'nombre' => 'Semestre 1'],
            ['codigo' => 2, 'nombre' => 'Semestre 2'],
            ['codigo' => 3, 'nombre' => 'Verano'],
            ['codigo' => 4, 'nombre' => 'Invierno'],
        ], ['codigo'], ['nombre']);

        $type = fn (int $code) => (int) DB::table('periodo')->where('codigo', $code)->value('id_periodo');

        // Solo un período puede ser el vigente: se libera el anterior antes de fijar el de prueba.
        DB::table('gestion_periodo')
            ->where('vigente', true)
            ->where('id_gestion_periodo', '!=', Ids::PERIOD_ACTIVE)
            ->update(['vigente' => false]);

        DB::table('gestion_periodo')->upsert([
            [
                'id_gestion_periodo' => Ids::PERIOD_2025_2,
                'id_gestion' => Ids::YEAR_2025,
                'id_periodo' => $type(2),
                'vigente' => false,
                'estado' => 'ACTIVO',
            ],
            [
                'id_gestion_periodo' => Ids::PERIOD_2026_1,
                'id_gestion' => Ids::YEAR_2026,
                'id_periodo' => $type(1),
                'vigente' => false,
                'estado' => 'ACTIVO',
            ],
            [
                'id_gestion_periodo' => Ids::PERIOD_ACTIVE,
                'id_gestion' => Ids::YEAR_2026,
                'id_periodo' => $type(2),
                'vigente' => true,
                'estado' => 'ACTIVO',
            ],
        ], ['id_gestion_periodo'], ['id_gestion', 'id_periodo', 'vigente', 'estado']);

        $offerings = DB::table('materia_carrera')
            ->where('estado', 'ACTIVO')
            ->whereIn('id_carrera', [
                Ids::CAREER_SYSTEMS,
                Ids::CAREER_INFORMATICS,
                Ids::CAREER_ECONOMICS,
                Ids::CAREER_BUSINESS,
            ])
            ->get(['id_carrera', 'id_materia'])
            ->map(fn ($pair) => [
                'id_carrera' => $pair->id_carrera,
                'id_materia' => $pair->id_materia,
                'id_gestion_periodo' => Ids::PERIOD_ACTIVE,
                'estado' => 'ACTIVO',
            ])
            ->all();

        DB::table('oferta_academica')->upsert(
            $offerings,
            ['id_carrera', 'id_materia', 'id_gestion_periodo'],
            ['estado']
        );
    }

    private function faculty(int $id, string $name, string $code): array
    {
        return [
            'id_facultad' => $id,
            'nombre' => $name,
            'codigo' => $code,
            'descripcion' => null,
            'estado' => 'ACTIVO',
        ];
    }

    private function career(int $id, string $name, string $code, int $facultyId): array
    {
        return [
            'id_carrera' => $id,
            'nombre' => $name,
            'codigo' => $code,
            'descripcion' => null,
            'estado' => 'ACTIVO',
            'id_facultad' => $facultyId,
        ];
    }

    private function subject(int $id, string $name, string $code, string $status = 'ACTIVO'): array
    {
        return [
            'id_materia' => $id,
            'nombre' => $name,
            'codigo' => $code,
            'descripcion' => null,
            'estado' => $status,
        ];
    }

    private function pair(
        int $careerId,
        int $subjectId,
        string $semester,
        bool $required,
        string $status = 'ACTIVO'
    ): array {
        return [
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'nivel_semestre' => $semester,
            'obligatoria' => $required,
            'estado' => $status,
        ];
    }

    private function classroom(int $id, string $room, int $capacity, string $location, string $status = 'ACTIVO'): array
    {
        return [
            'id_ambiente' => $id,
            'nro_aula' => $room,
            'capacidad' => $capacity,
            'ubicacion' => $location,
            'estado' => $status,
        ];
    }
}
