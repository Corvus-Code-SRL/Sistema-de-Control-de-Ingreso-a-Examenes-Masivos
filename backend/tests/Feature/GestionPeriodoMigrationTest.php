<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Las migraciones 2026_10_12_000001 (gestión y período) y 2026_10_12_000002 (oferta académica) sobre
 * una base con la forma ANTERIOR: se revierten, se siembran filas con la forma antigua y se vuelven a
 * aplicar. Todo corre dentro de una transacción (el DDL de PostgreSQL es transaccional) que se
 * descarta al final, así el esquema y los datos de las demás pruebas quedan intactos.
 */
class GestionPeriodoMigrationTest extends TestCase
{
    private const NORMALIZE = '2026_10_12_000001_normalize_gestion_and_periodo.php';
    private const OFFERING = '2026_10_12_000002_create_oferta_academica.php';

    private int $careerId;
    private int $calculusId;
    private int $algebraId;
    private int $archivedId;

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/' . self::NORMALIZE);
        require_once database_path('migrations/' . self::OFFERING);
    }

    protected function tearDown(): void
    {
        putenv('SCIEM_PERIODO_ACTIVO_ID');
        unset($_ENV['SCIEM_PERIODO_ACTIVO_ID'], $_SERVER['SCIEM_PERIODO_ACTIVO_ID']);

        parent::tearDown();
    }

    /** Revierte las dos migraciones y deja la base con las tablas anteriores (periodo con nombre y año). */
    private function toLegacyShape(): void
    {
        (new \CreateOfertaAcademica())->down();
        (new \NormalizeGestionAndPeriodo())->down();

        $this->assertTrue(Schema::hasColumns('periodo', ['id_periodo', 'nombre_periodo', 'gestion']));
        $this->assertTrue(Schema::hasColumn('grupo', 'gestion'));
        $this->assertFalse(Schema::hasTable('oferta_academica'));
    }

    private function migrateUp(): void
    {
        (new \NormalizeGestionAndPeriodo())->up();
        (new \CreateOfertaAcademica())->up();
    }

    private function seedLegacyPeriods(array $periods): void
    {
        foreach ($periods as [$id, $name, $year]) {
            DB::table('periodo')->insert(['id_periodo' => $id, 'nombre_periodo' => $name, 'gestion' => $year]);
        }
    }

    private function seedLegacyCatalogAndGroups(array $groups): string
    {
        $facultyId = DB::table('facultad')->insertGetId(
            ['nombre' => 'FCYT', 'codigo' => 'FCYT', 'estado' => 'ACTIVO'],
            'id_facultad'
        );
        $this->careerId = DB::table('carrera')->insertGetId(
            ['nombre' => 'Sistemas', 'codigo' => 'SIS', 'estado' => 'ACTIVO', 'id_facultad' => $facultyId],
            'id_carrera'
        );
        $this->calculusId = DB::table('materia')->insertGetId(
            ['nombre' => 'Cálculo', 'codigo' => 'M-1', 'estado' => 'ACTIVO'],
            'id_materia'
        );
        $this->algebraId = DB::table('materia')->insertGetId(
            ['nombre' => 'Álgebra', 'codigo' => 'M-2', 'estado' => 'ACTIVO'],
            'id_materia'
        );
        $this->archivedId = DB::table('materia')->insertGetId(
            ['nombre' => 'Archivada', 'codigo' => 'M-3', 'estado' => 'ACTIVO'],
            'id_materia'
        );
        DB::table('materia_carrera')->insert([
            ['id_carrera' => $this->careerId, 'id_materia' => $this->calculusId, 'estado' => 'ACTIVO'],
            ['id_carrera' => $this->careerId, 'id_materia' => $this->algebraId, 'estado' => 'ACTIVO'],
            ['id_carrera' => $this->careerId, 'id_materia' => $this->archivedId, 'estado' => 'INACTIVO'],
        ]);

        $teacherId = User::factory()->create()->id_usuario;

        foreach ($groups as [$subjectId, $number, $year, $periodId]) {
            DB::table('grupo')->insert([
                'id_carrera' => $this->careerId,
                'id_materia' => $subjectId,
                'num_grupo' => $number,
                'gestion' => $year,
                'estado' => 'ACTIVO',
                'id_usuario_docente' => $teacherId,
                'id_periodo' => $periodId,
            ]);
        }

        return $teacherId;
    }

    private function activateInEnvironment(?int $id): void
    {
        $value = $id === null ? false : (string) $id;

        putenv($value === false ? 'SCIEM_PERIODO_ACTIVO_ID' : 'SCIEM_PERIODO_ACTIVO_ID=' . $value);

        if ($value === false) {
            unset($_ENV['SCIEM_PERIODO_ACTIVO_ID'], $_SERVER['SCIEM_PERIODO_ACTIVO_ID']);

            return;
        }

        $_ENV['SCIEM_PERIODO_ACTIVO_ID'] = $_SERVER['SCIEM_PERIODO_ACTIVO_ID'] = $value;
    }

    public function test_normaliza_los_periodos_existentes_conservando_ids_y_grupos(): void
    {
        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->seedLegacyPeriods([
                [1, '1-2026', 2026],
                [2, '2-2026', 2026],
                [9301, '2026-3', 2026], // forma «AAAA-N» de los datos de prueba
                [9302, '2/2025', 2025],
            ]);
            $this->seedLegacyCatalogAndGroups([]);

            $this->activateInEnvironment(2);

            $this->migrateUp();

            // Gestiones sin repetir y catálogo fijo.
            $this->assertSame([2025, 2026], DB::table('gestion')->orderBy('anio')->pluck('anio')->map(fn ($a) => (int) $a)->all());
            $this->assertSame(
                ['0:Anual', '1:1', '2:2', '3:Verano', '4:Invierno'],
                DB::table('periodo')->orderBy('codigo')->get()->map(fn ($p) => $p->codigo . ':' . $p->nombre)->all()
            );

            // Las instancias conservan su id y apuntan al (año, período) que decía su nombre.
            $rows = DB::table('gestion_periodo')
                ->join('gestion', 'gestion.id_gestion', '=', 'gestion_periodo.id_gestion')
                ->join('periodo', 'periodo.id_periodo', '=', 'gestion_periodo.id_periodo')
                ->orderBy('gestion_periodo.id_gestion_periodo')
                ->get(['gestion_periodo.id_gestion_periodo as id', 'gestion.anio', 'periodo.codigo', 'gestion_periodo.activo']);

            $summary = $rows->mapWithKeys(fn ($r) => [(int) $r->id => $r->codigo . '/' . $r->anio . ($r->activo ? '*' : '')])->all();

            $this->assertSame([1 => '1/2026', 2 => '2/2026*', 9301 => '3/2026', 9302 => '2/2025'], $summary);

            // Solo uno activo: el que apuntaba SCIEM_PERIODO_ACTIVO_ID.
            $this->assertSame(1, DB::table('gestion_periodo')->where('activo', true)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_rellena_la_oferta_con_los_pares_activos_y_los_pares_de_los_grupos_existentes(): void
    {
        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->seedLegacyPeriods([[1, '1-2026', 2026], [2, '2-2026', 2026]]);

            // Cálculo en el período 2 (activo), Archivada (par INACTIVO) en el período 1.
            $this->seedLegacyCatalogAndGroups([]);
            $teacherId = DB::table('usuario')->orderByDesc('id_usuario')->value('id_usuario');
            DB::table('grupo')->insert([
                [
                    'id_carrera' => $this->careerId, 'id_materia' => $this->calculusId, 'num_grupo' => '1',
                    'gestion' => '2026', 'estado' => 'ACTIVO', 'id_usuario_docente' => $teacherId, 'id_periodo' => 2,
                ],
                [
                    'id_carrera' => $this->careerId, 'id_materia' => $this->calculusId, 'num_grupo' => '2',
                    'gestion' => '2026', 'estado' => 'ACTIVO', 'id_usuario_docente' => $teacherId, 'id_periodo' => 2,
                ],
                [
                    'id_carrera' => $this->careerId, 'id_materia' => $this->archivedId, 'num_grupo' => '1',
                    'gestion' => '2026', 'estado' => 'ACTIVO', 'id_usuario_docente' => $teacherId, 'id_periodo' => 1,
                ],
            ]);

            $this->activateInEnvironment(2);
            $this->migrateUp();

            $offerings = DB::table('oferta_academica')
                ->orderBy('id_gestion_periodo')->orderBy('id_materia')
                ->get()
                ->map(fn ($o) => "{$o->id_materia}@{$o->id_gestion_periodo}:{$o->estado}")
                ->all();

            $this->assertSame([
                "{$this->archivedId}@1:INACTIVO",  // par usado por un grupo, hereda el estado del par
                "{$this->calculusId}@2:ACTIVO",
                "{$this->algebraId}@2:ACTIVO",     // par ACTIVO sin grupos, en el período activo
            ], $offerings);

            // Cada grupo conserva su período y la clave compuesta a la oferta se cumple.
            $this->assertSame(3, DB::table('grupo')->whereIn('id_gestion_periodo', [1, 2])->count());
            $this->assertFalse(Schema::hasColumn('grupo', 'gestion'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_sin_variable_de_entorno_ningun_periodo_queda_activo(): void
    {
        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->seedLegacyPeriods([[1, '1-2026', 2026]]);
            $this->seedLegacyCatalogAndGroups([]);
            $this->activateInEnvironment(null);

            $this->migrateUp();

            $this->assertSame(0, DB::table('gestion_periodo')->where('activo', true)->count());
            // Sin período activo solo se ofertan los pares ya usados por grupos (aquí, ninguno).
            $this->assertSame(0, DB::table('oferta_academica')->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_aborta_con_las_filas_culpables_si_un_nombre_no_se_entiende(): void
    {
        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->seedLegacyPeriods([[1, '1-2026', 2026], [7, 'Verano-26', 2026]]);
            $this->activateInEnvironment(1);

            try {
                (new \NormalizeGestionAndPeriodo())->up();
                $this->fail('La migración debía abortar.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('no se modificó nada', $exception->getMessage());
                $this->assertStringContainsString('Verano-26', $exception->getMessage());
            }

            // Nada cambió: la tabla antigua sigue ahí con sus dos filas.
            $this->assertSame(2, DB::table('periodo')->count());
            $this->assertFalse(Schema::hasTable('gestion_periodo'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_aborta_si_el_ano_del_nombre_no_coincide_o_dos_periodos_colisionan(): void
    {
        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->seedLegacyPeriods([[1, '1-2026', 2026], [2, '2026-1', 2026], [3, '2-2025', 2026]]);

            try {
                (new \NormalizeGestionAndPeriodo())->up();
                $this->fail('La migración debía abortar.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('no coincide con gestion', $exception->getMessage());
                $this->assertStringContainsString('mismo (año, período)', $exception->getMessage());
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_aborta_si_dos_grupos_chocarian_sin_la_gestion_textual(): void
    {
        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->seedLegacyPeriods([[1, '1-2026', 2026]]);
            $this->seedLegacyCatalogAndGroups([]);
            $teacherId = DB::table('usuario')->orderByDesc('id_usuario')->value('id_usuario');

            // Mismo par, número y período; solo difieren en el texto de grupo.gestion.
            foreach (['2026', '2026-A'] as $gestion) {
                DB::table('grupo')->insert([
                    'id_carrera' => $this->careerId, 'id_materia' => $this->calculusId, 'num_grupo' => '1',
                    'gestion' => $gestion, 'estado' => 'ACTIVO', 'id_usuario_docente' => $teacherId, 'id_periodo' => 1,
                ]);
            }

            try {
                (new \NormalizeGestionAndPeriodo())->up();
                $this->fail('La migración debía abortar.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('chocarían en unq_grupo', $exception->getMessage());
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * `creation-script.sql` debe describir el mismo esquema que dejan las migraciones. Se compara,
     * dentro de la transacción, la firma (columnas, restricciones e índices) de las tablas tocadas:
     * una vez aplicando el bloque «Versión 8» del script y otra con las migraciones.
     */
    public function test_el_bloque_del_script_deja_el_mismo_esquema_que_las_migraciones(): void
    {
        $script = str_replace("\r\n", "\n", file_get_contents(base_path('../docs/database/creation-script.sql')));
        $start = strpos($script, '-- Versión 8: gestión, período y oferta académica');

        $this->assertNotFalse($start, 'El script no tiene el bloque de la Versión 8.');

        $block = substr($script, $start);

        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            DB::unprepared($block);
            $fromScript = $this->schemaSignature();
        } finally {
            DB::rollBack();
        }

        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->migrateUp();
            $fromMigrations = $this->schemaSignature();
        } finally {
            DB::rollBack();
        }

        $this->assertNotEmpty($fromMigrations);
        $this->assertSame($fromMigrations, $fromScript);
    }

    /** @return string[] */
    private function schemaSignature(): array
    {
        $tables = "'gestion', 'periodo', 'gestion_periodo', 'oferta_academica', 'grupo', 'convocatoria_admision'";

        $rows = DB::select(
            "select 'col' as kind, table_name || '.' || column_name || ':' || data_type || ':' || is_nullable
                    || ':' || coalesce(column_default, '') || ':' || coalesce(is_identity, '') as item
               from information_schema.columns
              where table_schema = 'public' and table_name in ({$tables})
             union all
             select 'con', conrelid::regclass::text || '.' || conname || ':' || pg_get_constraintdef(oid)
               from pg_constraint
              where connamespace = 'public'::regnamespace
                and conrelid in (select oid from pg_class
                                  where relnamespace = 'public'::regnamespace and relname in ({$tables}))
             union all
             select 'idx', tablename || '.' || indexname || ':' || indexdef
               from pg_indexes
              where schemaname = 'public' and tablename in ({$tables})
             order by 1, 2"
        );

        return array_map(fn ($row) => $row->kind . ' ' . $row->item, $rows);
    }

    public function test_el_down_restaura_nombre_y_gestion_originales(): void
    {
        DB::beginTransaction();

        try {
            $this->toLegacyShape();
            $this->seedLegacyPeriods([[1, '1-2026', 2026], [9301, '2026-3', 2026]]);
            $this->seedLegacyCatalogAndGroups([]);
            $this->activateInEnvironment(1);

            $this->migrateUp();

            (new \CreateOfertaAcademica())->down();
            (new \NormalizeGestionAndPeriodo())->down();

            // «AAAA-N» vuelve en la forma «N-AAAA», que es la de la base compartida.
            $this->assertSame(
                [1 => '1-2026', 9301 => '3-2026'],
                DB::table('periodo')->orderBy('id_periodo')->pluck('nombre_periodo', 'id_periodo')->all()
            );
            $this->assertSame(
                [2026],
                DB::table('periodo')->distinct()->pluck('gestion')->map(fn ($y) => (int) $y)->all()
            );
        } finally {
            DB::rollBack();
        }
    }
}
