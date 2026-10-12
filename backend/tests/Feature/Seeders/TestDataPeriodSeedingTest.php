<?php

namespace Tests\Feature\Seeders;

use App\Models\Group;
use App\Models\Period;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\TestData\CatalogTestDataSeeder;
use Database\Seeders\TestData\GroupTestDataSeeder;
use Database\Seeders\TestData\TestDataIds as Ids;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los datos de prueba compartidos arman gestiones, períodos y oferta consistentes: el período activo
 * (9303) sale de gestion_periodo.activo y todo grupo sembrado tiene su par ofertado en su período.
 */
class TestDataPeriodSeedingTest extends TestCase
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
        ]);
    }

    public function test_siembra_las_gestiones_y_los_periodos_con_el_9303_activo(): void
    {
        $this->seedTestData();

        $this->assertSame(
            [Ids::YEAR_2025 => 2025, Ids::YEAR_2026 => 2026],
            DB::table('gestion')->whereIn('id_gestion', [Ids::YEAR_2025, Ids::YEAR_2026])
                ->pluck('anio', 'id_gestion')->map(fn ($y) => (int) $y)->all()
        );

        $labels = Period::query()->withLabel()
            ->whereIn('gestion_periodo.id_gestion_periodo', [Ids::PERIOD_2025_2, Ids::PERIOD_2026_1, Ids::PERIOD_ACTIVE])
            ->orderBy('gestion_periodo.id_gestion_periodo')
            ->get()
            ->mapWithKeys(fn (Period $period) => [$period->id_gestion_periodo => $period->label . ($period->activo ? '*' : '')])
            ->all();

        $this->assertSame(
            [Ids::PERIOD_2025_2 => '2/2025', Ids::PERIOD_2026_1 => '1/2026', Ids::PERIOD_ACTIVE => '2/2026*'],
            $labels
        );

        $this->assertSame(
            [Ids::PERIOD_ACTIVE],
            Period::query()->active()->pluck('id_gestion_periodo')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_cada_grupo_sembrado_tiene_su_par_ofertado_en_su_periodo(): void
    {
        $this->seedTestData();

        $groups = Group::query()
            ->whereBetween('id_grupo', [Ids::GROUP_CALCULUS_SYS_1, Ids::GROUP_ACCOUNTING_ADM_1])
            ->get();

        $this->assertCount(11, $groups);

        foreach ($groups as $group) {
            $this->assertTrue(
                DB::table('oferta_academica')
                    ->where('id_carrera', $group->id_carrera)
                    ->where('id_materia', $group->id_materia)
                    ->where('id_gestion_periodo', $group->id_gestion_periodo)
                    ->exists(),
                "El grupo {$group->id_grupo} no tiene su par ofertado en su período."
            );
        }

        // El grupo de un período anterior convive con el del activo en el mismo par.
        $this->assertSame(
            Ids::PERIOD_2026_1,
            (int) Group::query()->whereKey(Ids::GROUP_CALCULUS_SYS_1_PREVIOUS)->value('id_gestion_periodo')
        );
    }

    public function test_los_pares_activos_quedan_ofertados_en_el_periodo_activo_y_el_inactivo_no(): void
    {
        $this->seedTestData();

        $offered = fn (int $careerId, int $subjectId) => DB::table('oferta_academica')
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->where('id_gestion_periodo', Ids::PERIOD_ACTIVE)
            ->where('estado', 'ACTIVO')
            ->exists();

        $this->assertTrue($offered(Ids::CAREER_SYSTEMS, Ids::SUBJECT_CALCULUS));
        $this->assertTrue($offered(Ids::CAREER_ECONOMICS, Ids::SUBJECT_MICROECONOMICS));

        // Par INACTIVO de Informática: tiene un grupo, así que existe una oferta, pero INACTIVA.
        $this->assertFalse($offered(Ids::CAREER_INFORMATICS, Ids::SUBJECT_DATABASES));
        $this->assertSame(
            'INACTIVO',
            DB::table('oferta_academica')
                ->where('id_carrera', Ids::CAREER_INFORMATICS)
                ->where('id_materia', Ids::SUBJECT_DATABASES)
                ->where('id_gestion_periodo', Ids::PERIOD_ACTIVE)
                ->value('estado')
        );
    }

    public function test_sembrar_dos_veces_es_idempotente(): void
    {
        $this->seedTestData();
        $offeringsAfterFirst = DB::table('oferta_academica')->count();
        $periodsAfterFirst = DB::table('gestion_periodo')->count();

        $this->seedTestData();

        $this->assertSame($offeringsAfterFirst, DB::table('oferta_academica')->count());
        $this->assertSame($periodsAfterFirst, DB::table('gestion_periodo')->count());
        $this->assertSame(1, Period::query()->active()->count());
    }
}
