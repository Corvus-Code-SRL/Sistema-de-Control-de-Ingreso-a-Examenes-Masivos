<?php

namespace Tests\Feature\Seeders;

use App\Models\Career;
use App\Models\Period;
use App\Services\Academic\SubjectCareerAssignmentService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExamTypeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\TestData\AssignmentSampleTestDataSeeder;
use Database\Seeders\TestData\CatalogTestDataSeeder;
use Database\Seeders\TestData\ExamTestDataSeeder;
use Database\Seeders\TestData\GroupTestDataSeeder;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** La muestra de HU-006 deja 1/2026 y 2/2026 con una electiva ofertada en uno solo de los dos. */
class AssignmentSampleSeedingTest extends TestCase
{
    use DatabaseTransactions;

    private function seedTestData(): void
    {
        $this->seed([
            RoleSeeder::class,
            ActionSeeder::class,
            AccountTestDataSeeder::class,
            CatalogTestDataSeeder::class,
            GroupTestDataSeeder::class,
            AssignmentSampleTestDataSeeder::class,
        ]);
    }

    private function offeredIn(int $periodId): bool
    {
        return DB::table('oferta_academica')
            ->where('id_carrera', Ids::CAREER_SYSTEMS)
            ->where('id_materia', Ids::SUBJECT_ELECTIVE_SAMPLE)
            ->where('id_gestion_periodo', $periodId)
            ->where('estado', 'ACTIVO')
            ->exists();
    }

    public function test_la_electiva_se_oferta_solo_en_uno_de_los_dos_periodos(): void
    {
        $this->seedTestData();

        $this->assertTrue($this->offeredIn(Ids::PERIOD_2026_1));
        $this->assertFalse($this->offeredIn(Ids::PERIOD_ACTIVE));
    }

    public function test_sembrar_otra_vez_es_idempotente_y_la_electiva_sigue_en_un_solo_periodo(): void
    {
        $this->seedTestData();
        $offerings = DB::table('oferta_academica')->count();
        $periods = DB::table('gestion_periodo')->count();
        $subjects = DB::table('materia')->count();

        $this->seedTestData();

        $this->assertSame($offerings, DB::table('oferta_academica')->count());
        $this->assertSame($periods, DB::table('gestion_periodo')->count());
        $this->assertSame($subjects, DB::table('materia')->count());
        $this->assertTrue($this->offeredIn(Ids::PERIOD_2026_1));
        $this->assertFalse($this->offeredIn(Ids::PERIOD_ACTIVE));
    }

    public function test_los_periodos_1_y_2_de_2026_existen_con_su_etiqueta(): void
    {
        $this->seedTestData();

        $labels = Period::query()->withLabel()
            ->where('gestion.anio', 2026)
            ->orderBy('periodo.codigo')
            ->get()
            ->map(fn (Period $period) => $period->label)
            ->all();

        $this->assertSame(['1/2026', '2/2026'], $labels);
    }

    public function test_no_cambia_el_periodo_vigente_si_ya_hay_uno(): void
    {
        $this->seedTestData();

        Period::query()->update(['vigente' => false]);
        Period::query()->whereKey(Ids::PERIOD_2026_1)->update(['vigente' => true]);

        $this->seed(AssignmentSampleTestDataSeeder::class);

        $this->assertSame(
            [Ids::PERIOD_2026_1],
            Period::query()->current()->pluck('id_gestion_periodo')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_sin_periodo_vigente_fija_2_2026_y_con_uno_no_agrega_otro(): void
    {
        $this->seedTestData();
        Period::query()->update(['vigente' => false]);

        $this->seed(AssignmentSampleTestDataSeeder::class);

        $this->assertSame(
            [Ids::PERIOD_ACTIVE],
            Period::query()->current()->pluck('id_gestion_periodo')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_ingenieria_industrial_queda_activa_sin_pares_ni_ofertas_en_ningun_periodo(): void
    {
        $this->seedTestData();

        $career = Career::query()->findOrFail(Ids::CAREER_NO_OFFERINGS_SAMPLE);

        $this->assertSame('Ingeniería Industrial', $career->nombre);
        $this->assertSame('ACTIVO', $career->estado);
        $this->assertSame(
            0,
            DB::table('oferta_academica')->where('id_carrera', Ids::CAREER_NO_OFFERINGS_SAMPLE)->count()
        );
        $this->assertSame(
            0,
            DB::table('materia_carrera')->where('id_carrera', Ids::CAREER_NO_OFFERINGS_SAMPLE)->count()
        );
        $this->assertSame(0, DB::table('grupo')->where('id_carrera', Ids::CAREER_NO_OFFERINGS_SAMPLE)->count());
    }

    public function test_en_el_vigente_hay_materia_asignable_a_una_carrera_donde_el_par_no_existe(): void
    {
        $this->seedTestData();

        $service = app(SubjectCareerAssignmentService::class);
        $period = $service->activePeriod(Ids::PERIOD_ACTIVE);
        $assignable = $service
            ->listAssignableSubjects(Career::query()->findOrFail(Ids::CAREER_NO_OFFERINGS_SAMPLE), $period)
            ->pluck('id_materia')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue($period->vigente);
        $this->assertTrue($assignable->contains(Ids::SUBJECT_CALCULUS));
        $this->assertFalse(
            DB::table('materia_carrera')
                ->where('id_carrera', Ids::CAREER_NO_OFFERINGS_SAMPLE)
                ->where('id_materia', Ids::SUBJECT_CALCULUS)
                ->exists()
        );
    }

    public function test_el_vigente_ofrece_calculo_a_sistemas_y_al_menos_tres_carreras_tienen_ofertas(): void
    {
        $this->seedTestData();

        $service = app(SubjectCareerAssignmentService::class);
        $period = $service->activePeriod(Ids::PERIOD_ACTIVE);

        $this->assertTrue(
            DB::table('oferta_academica')
                ->where('id_carrera', Ids::CAREER_SYSTEMS)
                ->where('id_materia', Ids::SUBJECT_CALCULUS)
                ->where('id_gestion_periodo', Ids::PERIOD_ACTIVE)
                ->where('estado', 'ACTIVO')
                ->exists()
        );
        $this->assertFalse(
            $service
                ->listAssignableSubjects(Career::query()->findOrFail(Ids::CAREER_SYSTEMS), $period)
                ->contains('id_materia', Ids::SUBJECT_CALCULUS)
        );
        $this->assertGreaterThanOrEqual(
            3,
            DB::table('oferta_academica')
                ->where('id_gestion_periodo', Ids::PERIOD_ACTIVE)
                ->where('estado', 'ACTIVO')
                ->distinct()
                ->count('id_carrera')
        );
    }

    public function test_la_carrera_nueva_no_altera_los_datos_de_prueba_ya_existentes(): void
    {
        $this->seed([
            RoleSeeder::class,
            ActionSeeder::class,
            AccountTestDataSeeder::class,
            CatalogTestDataSeeder::class,
            GroupTestDataSeeder::class,
        ]);

        $original = [
            Ids::CAREER_SYSTEMS,
            Ids::CAREER_INFORMATICS,
            Ids::CAREER_ECONOMICS,
            Ids::CAREER_BUSINESS,
        ];
        $snapshot = fn () => [
            'carreras' => DB::table('carrera')->count(),
            'grupos' => DB::table('grupo')->count(),
            'pares' => DB::table('materia_carrera')->whereIn('id_carrera', $original)->count(),
            'ofertas' => DB::table('oferta_academica')->whereIn('id_carrera', $original)->count(),
            'estudiantes' => DB::table('estudiante')->count(),
        ];
        $before = $snapshot();

        $this->seed(AssignmentSampleTestDataSeeder::class);
        $after = $snapshot();

        $this->assertSame($before['carreras'] + 1, $after['carreras']);
        $this->assertSame($before['grupos'], $after['grupos']);
        $this->assertSame($before['estudiantes'], $after['estudiantes']);
        // La electiva de muestra se suma al par y a la oferta de Sistemas, y a nada más.
        $this->assertSame($before['pares'] + 1, $after['pares']);
        $this->assertSame($before['ofertas'] + 1, $after['ofertas']);
    }

    public function test_el_sembrado_completo_de_datos_de_prueba_es_idempotente_con_la_carrera_nueva(): void
    {
        $seeders = [
            RoleSeeder::class,
            ActionSeeder::class,
            ExamTypeSeeder::class,
            CatalogTestDataSeeder::class,
            AccountTestDataSeeder::class,
            GroupTestDataSeeder::class,
            AssignmentSampleTestDataSeeder::class,
            ExamTestDataSeeder::class,
        ];

        $this->seed($seeders);
        $careers = DB::table('carrera')->count();
        $offerings = DB::table('oferta_academica')->count();

        $this->seed($seeders);

        $this->assertSame($careers, DB::table('carrera')->count());
        $this->assertSame($offerings, DB::table('oferta_academica')->count());
        $this->assertSame(
            1,
            DB::table('carrera')->where('id_carrera', Ids::CAREER_NO_OFFERINGS_SAMPLE)->count()
        );
    }

    public function test_no_forma_parte_de_database_seeder(): void
    {
        $source = file_get_contents((new \ReflectionClass(DatabaseSeeder::class))->getFileName());

        $this->assertStringNotContainsString('AssignmentSampleTestDataSeeder', $source);
        $this->assertStringNotContainsString('TestData', $source);
    }
}
