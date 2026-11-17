<?php

namespace Tests\Feature;

use App\Models\Exam;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-023: la migración que agrega el estudiante excepcional a examen_estudiante.
 *
 * Todo corre dentro de la transacción de la prueba (PostgreSQL admite DDL transaccional), así que el esquema
 * real queda intacto al terminar.
 */
class ExamenEstudianteExcepcionalMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    private const MIGRATION = '2026_11_17_000002_add_excepcional_to_examen_estudiante.php';

    private const REASON = 'Convalidación aprobada';

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/' . self::MIGRATION);
        $this->seedAssistantManagement();
    }

    private function migration(): \AddExcepcionalToExamenEstudiante
    {
        return new \AddExcepcionalToExamenEstudiante();
    }

    private function studentId(string $sis): int
    {
        return (int) DB::table('estudiante')->insertGetId([
            'cod_sis' => $sis,
            'ci' => $sis,
            'nombre' => 'Estudiante',
            'apellido_paterno' => $sis,
            'estado' => 'ACTIVO',
        ], 'id_estudiante');
    }

    private function enrolled(string $sis): int
    {
        $this->enrollStudentWithSis($this->grupoPropioId, $sis);

        return (int) DB::table('estudiante')->where('cod_sis', $sis)->value('id_estudiante');
    }

    /** Fila excepcional completa y válida para el examen base, con los datos que pide el CHECK. */
    private function exceptionalRow(int $studentId, array $overrides = []): array
    {
        $group = DB::table('grupo')->where('id_grupo', $this->grupoPropioId)->first();
        DB::table('grupo_oferta')->insertOrIgnore([
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $group->id_carrera,
            'id_materia' => $group->id_materia,
            'id_gestion_periodo' => $group->id_gestion_periodo,
        ]);

        return array_merge([
            'id_examen' => $this->examId,
            'id_estudiante' => $studentId,
            'id_grupo' => null,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
            'es_excepcional' => true,
            'motivo_excepcional' => self::REASON,
            'id_usuario_docente_excepcional' => $this->docenteId,
            'id_grupo_excepcional' => $this->grupoPropioId,
            'id_carrera_excepcional' => $group->id_carrera,
        ], $overrides);
    }

    private function assertRejected(callable $write, string $case = ''): void
    {
        DB::statement('SAVEPOINT antes_de_escribir');

        try {
            $write();
            $this->fail('La base debía rechazar la escritura. ' . $case);
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT antes_de_escribir');
        }
    }

    /** @return array<int, string> columnas, restricciones e índices de examen_estudiante, ordenados. */
    private function signature(): array
    {
        $rows = DB::select(
            "select 'col' as kind, column_name || ':' || data_type || ':' || is_nullable
                    || ':' || coalesce(column_default, '') || ':' || coalesce(generation_expression, '') as item
               from information_schema.columns
              where table_schema = 'public' and table_name = 'examen_estudiante'
             union all
             select 'con', conname || ':' || pg_get_constraintdef(oid)
               from pg_constraint where conrelid = 'public.examen_estudiante'::regclass
             union all
             select 'idx', indexname || ':' || indexdef
               from pg_indexes where schemaname = 'public' and tablename = 'examen_estudiante'
             order by 1, 2"
        );

        return array_map(fn ($row): string => $row->kind . ' ' . $row->item, $rows);
    }

    public function test_conserva_la_fk_de_la_nomina_y_la_unicidad_por_examen_y_estudiante(): void
    {
        $definitions = collect(DB::select(
            "select conname from pg_constraint where conrelid = 'public.examen_estudiante'::regclass"
        ))->pluck('conname')->all();

        $this->assertContains('fk_examen_estudiante_grupo_estudiante', $definitions);
        $this->assertContains('pk_examen_estudiante', $definitions);

        // Una fila normal sigue exigiendo que el estudiante figure en el grupo.
        $outsider = $this->studentId('202600555');
        $this->assertRejected(fn () => DB::table('examen_estudiante')->insert([
            'id_examen' => $this->examId,
            'id_estudiante' => $outsider,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
        ]));
    }

    public function test_el_check_solo_admite_una_fila_normal_o_un_excepcional_completo(): void
    {
        $student = $this->studentId('202600556');

        DB::table('examen_estudiante')->insert($this->exceptionalRow($student));
        $this->assertSame(
            $this->grupoPropioId,
            (int) DB::table('examen_estudiante')->where('id_estudiante', $student)->value('id_grupo_efectivo')
        );
        DB::table('examen_estudiante')->where('id_estudiante', $student)->delete();

        $invalid = [
            'excepcional con id_grupo' => ['id_grupo' => $this->grupoPropioId],
            'excepcional sin motivo' => ['motivo_excepcional' => null],
            'excepcional con motivo en blanco' => ['motivo_excepcional' => '   '],
            'excepcional sin docente' => ['id_usuario_docente_excepcional' => null],
            'excepcional sin grupo' => ['id_grupo_excepcional' => null],
            'excepcional sin carrera' => ['id_carrera_excepcional' => null],
            'normal sin id_grupo' => ['es_excepcional' => false, 'motivo_excepcional' => null,
                'id_usuario_docente_excepcional' => null, 'id_grupo_excepcional' => null,
                'id_carrera_excepcional' => null],
            'normal con datos de excepcional' => ['es_excepcional' => false, 'id_grupo' => $this->grupoPropioId],
        ];

        foreach ($invalid as $case => $overrides) {
            $this->assertRejected(fn () => DB::table('examen_estudiante')
                ->insert($this->exceptionalRow($student, $overrides)), $case);
        }
    }

    public function test_el_grupo_y_la_carrera_del_excepcional_deben_existir_en_el_examen_y_en_la_oferta(): void
    {
        $student = $this->studentId('202600557');

        $this->assertRejected(fn () => DB::table('examen_estudiante')
            ->insert($this->exceptionalRow($student, ['id_grupo_excepcional' => $this->grupoAjenoId])));
        $this->assertRejected(fn () => DB::table('examen_estudiante')
            ->insert($this->exceptionalRow($student, ['id_carrera_excepcional' => 2147483000])));
    }

    public function test_down_con_filas_existentes_no_pierde_ninguna_y_up_las_recupera(): void
    {
        $normal = $this->enrolled('202600558');
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->examId,
            'id_estudiante' => $normal,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '08:10:00',
        ]);
        $before = DB::table('examen_estudiante')->count();

        $this->migration()->down();
        $this->assertSame($before, DB::table('examen_estudiante')->count());
        $this->assertFalse(Schema::hasColumn('examen_estudiante', 'es_excepcional'));
        $this->assertSame('NO', DB::selectOne(
            "select is_nullable from information_schema.columns
              where table_name = 'examen_estudiante' and column_name = 'id_grupo'"
        )->is_nullable);

        $this->migration()->up();
        $this->assertSame($before, DB::table('examen_estudiante')->count());
        $row = DB::table('examen_estudiante')->where('id_estudiante', $normal)->first();
        $this->assertFalse((bool) $row->es_excepcional);
        $this->assertSame($this->grupoPropioId, (int) $row->id_grupo_efectivo);
        $this->assertSame('INGRESO', $row->estado_ingreso);
    }

    public function test_con_muchas_filas_la_migracion_no_pierde_ninguna_y_deja_las_restricciones_validas(): void
    {
        $examIds = [$this->examId];
        for ($i = 1; $i <= 2; $i++) {
            $examIds[] = $this->createExamLinkedTo([$this->grupoPropioId], Exam::EN_CURSO, null, "Examen {$i}");
        }

        $states = ['INGRESO', 'CON_RETRASO', 'NO_INGRESO'];
        for ($n = 1; $n <= 30; $n++) {
            $studentId = $this->enrolled(sprintf('2026020%02d', $n));
            foreach ($examIds as $examId) {
                $state = $states[$n % 3];
                DB::table('examen_estudiante')->insert([
                    'id_examen' => $examId,
                    'id_estudiante' => $studentId,
                    'id_grupo' => $this->grupoPropioId,
                    'estado_habilitacion' => $n % 7 === 0 ? 'NO_HABILITADO' : 'HABILITADO',
                    'estado_ingreso' => $state,
                    'hora_ingreso' => $state === 'NO_INGRESO' ? null : '08:15:00',
                ]);
            }
        }

        $snapshot = fn (): array => DB::table('examen_estudiante')
            ->orderBy('id_examen')->orderBy('id_estudiante')
            ->get(['id_examen', 'id_estudiante', 'id_grupo', 'estado_habilitacion', 'estado_ingreso', 'hora_ingreso'])
            ->map(fn ($row): array => (array) $row)->all();
        $before = $snapshot();
        $this->assertCount(90, $before);

        $this->migration()->down();
        $this->assertSame($before, $snapshot());

        $this->migration()->up();
        $this->assertSame($before, $snapshot());
        $this->assertSame(90, DB::table('examen_estudiante')->where('es_excepcional', false)->count());
        $this->assertSame(
            0,
            DB::table('examen_estudiante')->whereColumn('id_grupo', '<>', 'id_grupo_efectivo')->count()
        );

        $notValidated = DB::select(
            "select conname from pg_constraint
              where conrelid = 'public.examen_estudiante'::regclass and not convalidated"
        );
        $this->assertSame([], $notValidated);
    }

    public function test_down_se_niega_si_existen_estudiantes_excepcionales(): void
    {
        DB::table('examen_estudiante')->insert($this->exceptionalRow($this->studentId('202600559')));

        try {
            $this->migration()->down();
            $this->fail('El down debía abortar con excepcionales.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existen estudiantes excepcionales', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('examen_estudiante', 'es_excepcional'));
        $this->assertSame(1, DB::table('examen_estudiante')->where('es_excepcional', true)->count());
    }

    public function test_el_bloque_version_18_del_script_deja_el_mismo_esquema_que_la_migracion(): void
    {
        $script = str_replace("\r\n", "\n", file_get_contents(base_path('../docs/database/creation-script.sql')));
        $start = strpos($script, '-- Versión 18:');
        $this->assertNotFalse($start, 'El script no tiene el bloque de la Versión 18.');
        $block = substr($script, $start);

        $fromMigration = $this->signature();

        $this->migration()->down();
        DB::unprepared($block);
        $fromScript = $this->signature();

        $this->assertNotEmpty($fromMigration);
        $this->assertSame($fromMigration, $fromScript);
    }

    public function test_id_grupo_efectivo_es_una_columna_generada(): void
    {
        $this->assertSame('STORED', DB::selectOne(
            "select case when is_generated = 'ALWAYS' then 'STORED' end as g from information_schema.columns
              where table_name = 'examen_estudiante' and column_name = 'id_grupo_efectivo'"
        )->g);
    }
}
