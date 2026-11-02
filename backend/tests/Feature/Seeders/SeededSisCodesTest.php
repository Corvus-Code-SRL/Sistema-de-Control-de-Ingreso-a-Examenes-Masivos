<?php

namespace Tests\Feature\Seeders;

use App\Models\Role;
use App\Support\SisCodeFormat;
use Carbon\Carbon;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\TestData\AssignmentSampleTestDataSeeder;
use Database\Seeders\TestData\CatalogTestDataSeeder;
use Database\Seeders\TestData\ExamTestDataSeeder;
use Database\Seeders\TestData\GroupTestDataSeeder;
use Database\Seeders\TestData\RiskCenterTestDataSeeder;
use Database\Seeders\TestData\Sprint3QaSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Los códigos que siembran los datos de prueba compartidos cumplen la validación de cuentas (HU-001).
 *
 * TestDataSeeder y Sprint3QaSeeder se niegan a correr sobre sciem_test, así que se ejecutan por
 * separado los seeders de datos que sí admiten una base local (los mismos que TestDataSeeder, en
 * su orden) y, por reflexión, las dos partes de Sprint3QaSeeder que agregan cuentas y estudiantes.
 */
class SeededSisCodesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 09:00:00');

        $this->seed([RoleSeeder::class, ActionSeeder::class]);
        $this->seed([
            CatalogTestDataSeeder::class,
            AccountTestDataSeeder::class,
            GroupTestDataSeeder::class,
            AssignmentSampleTestDataSeeder::class,
            ExamTestDataSeeder::class,
            RiskCenterTestDataSeeder::class,
        ]);

        $qa = new Sprint3QaSeeder();
        foreach (['seedAccounts', 'seedStudents'] as $method) {
            $reflection = new ReflectionMethod($qa, $method);
            $reflection->setAccessible(true);
            $reflection->invoke($qa);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cada_codigo_de_cuenta_sembrado_es_valido_para_su_rol_vigente(): void
    {
        $accounts = DB::table('usuario')
            ->leftJoin('usuario_rol', function ($join) {
                $join->on('usuario_rol.id_usuario', '=', 'usuario.id_usuario')->whereNull('usuario_rol.fecha_fin');
            })
            ->leftJoin('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
            ->get(['usuario.cod_sis', 'rol.nombre_rol']);

        $this->assertGreaterThanOrEqual(10, $accounts->count());

        $invalid = $accounts
            ->reject(fn ($account) => SisCodeFormat::isValid($account->cod_sis, $account->nombre_rol))
            ->map(fn ($account) => "{$account->cod_sis} ({$account->nombre_rol})")
            ->all();

        $this->assertSame([], $invalid, 'Códigos de cuenta sembrados que la validación rechaza.');
    }

    /**
     * Un estudiante puede ser auxiliar, así que sus códigos de 8 y 9 dígitos deben pasar el formato de
     * Auxiliar, y todos deben cumplir la regla de la nómina (config sciem.estudiante_cod_sis).
     */
    public function test_cada_codigo_de_estudiante_sembrado_cumple_la_regla_de_la_nomina_y_los_de_8_o_9_digitos_la_de_auxiliar(): void
    {
        $codes = DB::table('estudiante')->pluck('cod_sis');
        $min = config('sciem.estudiante_cod_sis.min_length');
        $max = config('sciem.estudiante_cod_sis.max_length');

        $this->assertGreaterThanOrEqual(100, $codes->count());

        $outsideRoster = $codes
            ->reject(fn ($code) => preg_match('/^\d+$/', $code) === 1 && strlen($code) >= $min && strlen($code) <= $max)
            ->values()
            ->all();

        $notAssistantFormat = $codes
            ->filter(fn ($code) => in_array(strlen($code), [8, 9], true))
            ->reject(fn ($code) => SisCodeFormat::isValid($code, Role::AUXILIAR))
            ->values()
            ->all();

        $this->assertSame([], $outsideRoster, 'Códigos de estudiante fuera de la regla de la nómina.');
        $this->assertSame([], $notAssistantFormat, 'Códigos de estudiante de 8 o 9 dígitos que el formato de Auxiliar rechaza.');
    }

    public function test_los_153_estudiantes_de_qa_tienen_codigos_de_9_digitos_distintos_y_sembrarlos_de_nuevo_no_los_cambia(): void
    {
        $qaCodes = fn () => DB::table('estudiante')
            ->where('cod_sis', 'like', '20216%')
            ->orderBy('id_estudiante')
            ->pluck('cod_sis');
        $first = $qaCodes();

        $this->assertCount(153, $first);
        $this->assertSame([], $first->reject(fn ($code) => preg_match('/^\d{9}$/', $code) === 1)->values()->all());
        $this->assertCount(153, $first->unique());
        $this->assertSame('202160118', $first->get(117));

        $qa = new Sprint3QaSeeder();
        $reflection = new ReflectionMethod($qa, 'seedStudents');
        $reflection->setAccessible(true);
        $reflection->invoke($qa);

        $this->assertSame($first->all(), $qaCodes()->all());
    }

    public function test_ningun_codigo_sembrado_usa_el_prefijo_reservado_de_invitados(): void
    {
        $this->assertSame(0, DB::table('usuario')->where('cod_sis', 'like', 'INV%')->count());
        $this->assertSame(0, DB::table('estudiante')->where('cod_sis', 'like', 'INV%')->count());
    }
}
