<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function testItUsesOnlyTestDatabase(): void
    {
        $this->assertSame(
            'sciem_test',
            DB::connection()->getDatabaseName()
        );
    }

    public function testItLoadsRequiredAcademicTables(): void
    {
        $this->assertTrue(Schema::hasTable('estudiante'));
        $this->assertTrue(Schema::hasTable('grupo'));
        $this->assertTrue(Schema::hasTable('grupo_estudiante'));
        $this->assertTrue(Schema::hasTable('gestion'));
        $this->assertTrue(Schema::hasTable('periodo'));
        $this->assertTrue(Schema::hasTable('gestion_periodo'));
        $this->assertTrue(Schema::hasTable('oferta_academica'));
    }

    public function testStudentCiIsNullable(): void
    {
        $column = DB::selectOne(
            "select is_nullable from information_schema.columns
             where table_schema = 'public' and table_name = 'estudiante' and column_name = 'ci'"
        );

        $this->assertSame('YES', $column->is_nullable);
    }

    public function testStudentCiUniquenessToleratesSeveralNullsButRejectsRepeatedValues(): void
    {
        $this->insertStudent('202400001', null);
        $this->insertStudent('202400002', null);

        $this->assertSame(2, DB::table('estudiante')->whereNull('ci')->count());

        $this->insertStudent('202400003', '1234567');

        $this->expectException(QueryException::class);
        $this->insertStudent('202400004', '1234567');
    }

    public function testExamStateEnumHasExactlyTheFiveLifecycleValues(): void
    {
        $values = DB::selectOne(
            "select string_agg(e.enumlabel, ',' order by e.enumsortorder) as valores
             from pg_enum e join pg_type t on t.oid = e.enumtypid
             where t.typname = 'estado_examen'"
        )->valores;

        $this->assertSame('PROGRAMADO,EN_INGRESO,EN_CURSO,FINALIZADO,CANCELADO', $values);
    }

    public function testExamRequiresATeacherAndOpensTenMinutesEarlierByDefault(): void
    {
        $examId = $this->insertExam($this->insertUser('docente'));

        $exam = DB::table('examen')->where('id_examen', $examId)->first();
        $this->assertSame(10, $exam->minutos_apertura);
        $this->assertSame('PROGRAMADO', $exam->estado);

        $teacherColumn = DB::selectOne(
            "select is_nullable from information_schema.columns
             where table_schema = 'public' and table_name = 'examen' and column_name = 'id_usuario_docente'"
        );
        $this->assertSame('NO', $teacherColumn->is_nullable);
    }

    public function testOpeningMinutesStayBetweenZeroAndThirty(): void
    {
        $teacher = $this->insertUser('docente');

        $this->insertExam($teacher, ['minutos_apertura' => 0]);
        $this->insertExam($teacher, ['minutos_apertura' => 30]);

        $this->assertRejected(fn () => $this->insertExam($teacher, ['minutos_apertura' => 31]));
        $this->assertRejected(fn () => $this->insertExam($teacher, ['minutos_apertura' => -1]));
    }

    public function testGestionYearIsASmallintBetween2000And2100AndUnique(): void
    {
        $type = DB::selectOne(
            "select data_type from information_schema.columns
             where table_schema = 'public' and table_name = 'gestion' and column_name = 'anio'"
        );
        $this->assertSame('smallint', $type->data_type);

        DB::table('gestion')->insert(['anio' => 2026, 'estado' => 'ACTIVO']);

        $this->assertRejected(fn () => DB::table('gestion')->insert(['anio' => 1999, 'estado' => 'ACTIVO']));
        $this->assertRejected(fn () => DB::table('gestion')->insert(['anio' => 2101, 'estado' => 'ACTIVO']));
        $this->assertRejected(fn () => DB::table('gestion')->insert(['anio' => 2026, 'estado' => 'ACTIVO']));
    }

    public function testPeriodoIsAFixedCatalogOfFiveEntries(): void
    {
        $rows = DB::table('periodo')->orderBy('codigo')->get(['codigo', 'nombre'])
            ->map(fn ($row) => $row->codigo . ':' . $row->nombre)
            ->all();

        $this->assertSame(['0:Anual', '1:Semestre 1', '2:Semestre 2', '3:Verano', '4:Invierno'], $rows);

        $this->assertRejected(fn () => DB::table('periodo')->insert(['codigo' => 1, 'nombre' => 'Otro']));
        $this->assertRejected(fn () => DB::table('periodo')->insert(['codigo' => 9, 'nombre' => 'Semestre 1']));
    }

    public function testGestionPeriodoIsUniquePerYearAndPeriodWithASingleVigenteRow(): void
    {
        $yearId = DB::table('gestion')->insertGetId(['anio' => 2026, 'estado' => 'ACTIVO'], 'id_gestion');
        $first = (int) DB::table('periodo')->where('codigo', 1)->value('id_periodo');
        $second = (int) DB::table('periodo')->where('codigo', 2)->value('id_periodo');

        $firstId = DB::table('gestion_periodo')->insertGetId(
            ['id_gestion' => $yearId, 'id_periodo' => $first, 'vigente' => true],
            'id_gestion_periodo'
        );

        // (gestión, período) es único.
        $this->assertRejected(fn () => DB::table('gestion_periodo')->insert(
            ['id_gestion' => $yearId, 'id_periodo' => $first, 'vigente' => false]
        ));

        // Solo una fila puede ser la vigente; las demás no tienen tope.
        $this->assertRejected(fn () => DB::table('gestion_periodo')->insert(
            ['id_gestion' => $yearId, 'id_periodo' => $second, 'vigente' => true]
        ));
        DB::table('gestion_periodo')->insert(
            ['id_gestion' => $yearId, 'id_periodo' => $second, 'vigente' => false]
        );

        // Las fechas son opcionales, pero el fin no puede ser anterior al inicio.
        $this->assertRejected(fn () => DB::table('gestion_periodo')->where('id_gestion_periodo', $firstId)->update(
            ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-07-01']
        ));
        $this->assertSame(
            1,
            DB::table('gestion_periodo')->where('vigente', true)->count()
        );
    }

    public function testAGroupCanOnlyExistForAnOfferedPairInItsPeriod(): void
    {
        $teacher = $this->insertUser('docente');

        // insertGroup() ofrece el par en su período antes de crear el grupo.
        $groupId = $this->insertGroup($teacher);
        $group = DB::table('grupo')->where('id_grupo', $groupId)->first();

        $this->assertSame(
            1,
            DB::table('oferta_academica')
                ->where('id_carrera', $group->id_carrera)
                ->where('id_materia', $group->id_materia)
                ->where('id_gestion_periodo', $group->id_gestion_periodo)
                ->count()
        );

        // Otro período donde el par no se ofrece: la clave compuesta de grupo lo rechaza.
        $yearId = (int) DB::table('gestion')->value('id_gestion');
        $typeId = (int) DB::table('periodo')->where('codigo', 2)->value('id_periodo');
        $otherPeriod = DB::table('gestion_periodo')->insertGetId(
            ['id_gestion' => $yearId, 'id_periodo' => $typeId],
            'id_gestion_periodo'
        );

        $this->assertRejected(fn () => DB::table('grupo')->insert([
            'id_carrera' => $group->id_carrera,
            'id_materia' => $group->id_materia,
            'num_grupo' => '2',
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $teacher,
            'id_gestion_periodo' => $otherPeriod,
        ]));

        // La unicidad del grupo ya no incluye ninguna «gestión» textual.
        $this->assertRejected(fn () => DB::table('grupo')->insert([
            'id_carrera' => $group->id_carrera,
            'id_materia' => $group->id_materia,
            'num_grupo' => $group->num_grupo,
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $teacher,
            'id_gestion_periodo' => $group->id_gestion_periodo,
        ]));

        $this->assertFalse(Schema::hasColumn('grupo', 'gestion'));
        $this->assertFalse(Schema::hasColumn('grupo', 'id_periodo'));
        $this->assertFalse(Schema::hasColumn('convocatoria_admision', 'gestion'));
        $this->assertTrue(Schema::hasColumn('convocatoria_admision', 'id_gestion_periodo'));
    }

    public function testAnOfferingNeedsAnExistingSubjectCareerPair(): void
    {
        $yearId = DB::table('gestion')->insertGetId(['anio' => 2026, 'estado' => 'ACTIVO'], 'id_gestion');
        $typeId = (int) DB::table('periodo')->where('codigo', 1)->value('id_periodo');
        $periodId = DB::table('gestion_periodo')->insertGetId(
            ['id_gestion' => $yearId, 'id_periodo' => $typeId],
            'id_gestion_periodo'
        );

        $this->assertRejected(fn () => DB::table('oferta_academica')->insert([
            'id_carrera' => 987654,
            'id_materia' => 987654,
            'id_gestion_periodo' => $periodId,
        ]));
    }

    public function testAnExamCanOnlyBeCancelledWhileItIsScheduled(): void
    {
        $teacher = $this->insertUser('docente');

        $scheduled = $this->insertExam($teacher);
        DB::table('examen')->where('id_examen', $scheduled)->update(['estado' => 'CANCELADO']);
        $this->assertSame('CANCELADO', DB::table('examen')->where('id_examen', $scheduled)->value('estado'));

        $entering = $this->insertExam($teacher, ['estado' => 'EN_INGRESO']);
        $this->assertRejected(
            fn () => DB::table('examen')->where('id_examen', $entering)->update(['estado' => 'CANCELADO'])
        );

        $running = $this->insertExam($teacher, ['estado' => 'EN_CURSO']);
        $this->assertRejected(
            fn () => DB::table('examen')->where('id_examen', $running)->update(['estado' => 'CANCELADO'])
        );

        DB::table('examen')->where('id_examen', $entering)->update(['estado' => 'EN_CURSO']);
        $this->assertSame('EN_CURSO', DB::table('examen')->where('id_examen', $entering)->value('estado'));
    }

    public function testAuxiliaryTablesHaveCompositePrimaryKeysAndTheirForeignKeys(): void
    {
        $definitions = collect(DB::select(
            "select conname, pg_get_constraintdef(oid) as definicion from pg_constraint
             where conrelid in ('public.grupo_auxiliar'::regclass, 'public.examen_auxiliar'::regclass)"
        ))->pluck('definicion', 'conname');

        $this->assertSame('PRIMARY KEY (id_grupo, id_usuario)', $definitions['pk_grupo_auxiliar']);
        $this->assertSame('PRIMARY KEY (id_examen, id_usuario)', $definitions['pk_examen_auxiliar']);

        $foreignKeys = [
            'fk_grupo_auxiliar_grupo' => 'FOREIGN KEY (id_grupo) REFERENCES grupo(id_grupo)',
            'fk_grupo_auxiliar_usuario' => 'FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)',
            'fk_examen_auxiliar_examen' => 'FOREIGN KEY (id_examen) REFERENCES examen(id_examen)',
            'fk_examen_auxiliar_usuario' => 'FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)',
            'fk_examen_auxiliar_usuario_docente_habilita'
                => 'FOREIGN KEY (id_usuario_docente_habilita) REFERENCES usuario(id_usuario)',
            'fk_examen_auxiliar_examen_ambiente'
                => 'FOREIGN KEY (id_examen, id_ambiente) REFERENCES examen_ambiente(id_examen, id_ambiente)',
        ];

        foreach ($foreignKeys as $name => $definition) {
            $this->assertSame($definition, $definitions[$name], $name);
        }
    }

    public function testExamAuxiliaryClassroomMustBeOneOfTheExamClassrooms(): void
    {
        $teacher = $this->insertUser('docente');
        $auxiliary = $this->insertUser('auxiliar');
        $examId = $this->insertExam($teacher);
        $ownClassroom = $this->insertClassroom('691A');
        $foreignClassroom = $this->insertClassroom('692B');
        DB::table('examen_ambiente')->insert(['id_examen' => $examId, 'id_ambiente' => $ownClassroom]);

        $enabling = [
            'id_examen' => $examId,
            'id_usuario' => $auxiliary,
            'id_usuario_docente_habilita' => $teacher,
        ];

        $this->assertRejected(
            fn () => DB::table('examen_auxiliar')->insert($enabling + ['id_ambiente' => $foreignClassroom])
        );

        DB::table('examen_auxiliar')->insert($enabling + ['id_ambiente' => null]);
        DB::table('examen_auxiliar')->update(['id_ambiente' => $ownClassroom]);
        $this->assertSame($ownClassroom, DB::table('examen_auxiliar')->value('id_ambiente'));

        $this->assertRejected(fn () => DB::table('examen_auxiliar')->insert($enabling));
    }

    public function testGroupAuxiliaryIsUniquePerGroupAndUser(): void
    {
        $auxiliary = $this->insertUser('auxiliar');
        $row = [
            'id_grupo' => $this->insertGroup($this->insertUser('docente')),
            'id_usuario' => $auxiliary,
            'fecha_incorporacion' => '2026-03-01',
            'estado' => 'ACTIVO',
        ];

        DB::table('grupo_auxiliar')->insert($row);

        $this->assertRejected(fn () => DB::table('grupo_auxiliar')->insert($row));
    }

    public function testCreationScriptStartsWithTheUntouchedBaseline(): void
    {
        $normalize = fn (string $path) => str_replace("\r\n", "\n", file_get_contents($path));
        $baseline = $normalize(database_path('schema/baseline.sql'));
        $script = $normalize(base_path('../docs/database/creation-script.sql'));

        $this->assertStringStartsWith(
            $baseline,
            $script,
            'docs/database/creation-script.sql debe empezar con una copia exacta de '
            . 'database/schema/baseline.sql; los cambios posteriores se agregan al final.'
        );
    }

    /**
     * El script es el esquema final: lo que agregan las migraciones posteriores a la base debe
     * estar en él. Se comprueba contra la base que acaban de armar las migraciones.
     */
    public function testCreationScriptDeclaresWhatLaterMigrationsAdd(): void
    {
        $script = file_get_contents(base_path('../docs/database/creation-script.sql'));

        $this->assertTrue(Schema::hasTable('intento_ingreso'));
        $this->assertTrue(Schema::hasColumns('examen_estudiante', [
            'id_ambiente',
            'id_usuario_controlador',
            'registrado_en',
        ]));

        $constraints = collect(DB::select(
            "select conname from pg_constraint
             where conrelid in ('public.examen_estudiante'::regclass, 'public.intento_ingreso'::regclass)
               and conname !~ '^(pk_|examen_estudiante_)'"
        ))->pluck('conname');
        $indexes = collect(DB::select(
            "select indexname from pg_indexes
             where schemaname = 'public' and indexname in ('idx_ingreso_examen_registrado', 'idx_intento_examen_registrado')"
        ))->pluck('indexname');

        $objects = $constraints->merge($indexes)->merge(['intento_ingreso', 'registrado_en', 'id_usuario_controlador']);

        $this->assertGreaterThanOrEqual(8, $objects->count());

        foreach ($objects as $name) {
            $this->assertStringContainsString($name, $script, "El script no declara {$name}.");
        }
    }

    /** El esquema final de gestión, período y oferta académica está descrito en el script. */
    public function testCreationScriptDeclaresTheGestionPeriodoModel(): void
    {
        $script = file_get_contents(base_path('../docs/database/creation-script.sql'));

        $constraints = collect(DB::select(
            "select conname from pg_constraint
             where conrelid in (
                 'public.gestion'::regclass,
                 'public.periodo'::regclass,
                 'public.gestion_periodo'::regclass,
                 'public.oferta_academica'::regclass
             ) and contype in ('p', 'f', 'u', 'c')"
        ))->pluck('conname');

        $indexes = collect(DB::select(
            "select indexname from pg_indexes
             where schemaname = 'public'
               and indexname in (
                   'unq_gestion_periodo_vigente', 'idx_gestion_periodo_gestion', 'idx_gestion_periodo_periodo',
                   'idx_oferta_academica_gestion_periodo', 'idx_oferta_academica_materia_carrera',
                   'idx_grupo_gestion_periodo', 'idx_convocatoria_admision_gestion_periodo'
               )"
        ))->pluck('indexname');

        $grupoConstraints = collect(DB::select(
            "select conname from pg_constraint
             where conrelid = 'public.grupo'::regclass
               and conname in ('unq_grupo', 'fk_grupo_oferta_academica', 'fk_grupo_gestion_periodo')"
        ))->pluck('conname');

        $objects = $constraints->merge($indexes)->merge($grupoConstraints);

        $this->assertGreaterThanOrEqual(15, $objects->count());

        foreach ($objects as $name) {
            $this->assertStringContainsString($name, $script, "El script no declara {$name}.");
        }

        foreach (['gestion_periodo', 'oferta_academica', 'id_gestion_periodo', 'Invierno', 'Semestre 1', 'vigente'] as $word) {
            $this->assertStringContainsString($word, $script);
        }
    }

    public function testUsuarioTracksPendingPasswordChangeAndDefaultsToFalse(): void
    {
        $column = DB::selectOne(
            "select data_type, is_nullable, column_default from information_schema.columns
             where table_schema = 'public' and table_name = 'usuario'
               and column_name = 'debe_cambiar_contrasenia'"
        );

        $this->assertNotNull($column);
        $this->assertSame('boolean', $column->data_type);
        $this->assertSame('NO', $column->is_nullable);
        $this->assertSame('false', $column->column_default);

        $id = $this->insertUser('conindicador');

        $this->assertFalse(
            (bool) DB::table('usuario')->where('id_usuario', $id)->value('debe_cambiar_contrasenia')
        );
    }

    public function testUsuarioLastNamesAdmitSixtyCharactersButEstudianteKeepsItsLimit(): void
    {
        $widths = DB::select(
            "select table_name, column_name, character_maximum_length from information_schema.columns
             where table_schema = 'public' and column_name in ('apellido_paterno', 'apellido_materno')
               and table_name in ('usuario', 'estudiante')"
        );

        foreach ($widths as $column) {
            $expected = $column->table_name === 'usuario' ? 60 : 30;

            $this->assertSame(
                $expected,
                (int) $column->character_maximum_length,
                "{$column->table_name}.{$column->column_name}"
            );
        }

        $this->assertCount(4, $widths);
    }

    public function testCreationScriptDeclaresTheUsuarioChangesOfHu001(): void
    {
        $script = file_get_contents(base_path('../docs/database/creation-script.sql'));

        $this->assertStringContainsString(
            'ADD COLUMN debe_cambiar_contrasenia boolean NOT NULL DEFAULT false',
            $script
        );
        $this->assertStringContainsString('ALTER COLUMN apellido_paterno TYPE varchar(60)', $script);
        $this->assertStringContainsString('ALTER COLUMN apellido_materno TYPE varchar(60)', $script);
    }

    /** Corre la operación en un savepoint: la base debe rechazarla sin abortar la transacción del test. */
    private function assertRejected(callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());

            return;
        }

        $this->fail('La base de datos debía rechazar la operación.');
    }

    private function insertUser(string $prefix): string
    {
        return DB::table('usuario')->insertGetId([
            'nombre' => 'Nombre',
            'apellido_paterno' => 'Apellido',
            'correo' => $prefix . '.' . uniqid() . '@sciem.test',
            'contrasenia' => 'secreto',
            'cod_sis' => (string) random_int(100000000, 999999999),
            'estado' => 'ACTIVO',
        ], 'id_usuario');
    }

    private function insertClassroom(string $number): int
    {
        return DB::table('ambiente')->insertGetId(
            ['nro_aula' => $number, 'capacidad' => 40, 'estado' => 'ACTIVO'],
            'id_ambiente'
        );
    }

    private function insertExam(string $teacherId, array $overrides = []): int
    {
        $typeId = DB::table('tipo_examen')->where('nombre', 'Regular')->value('id_tipo_examen')
            ?? DB::table('tipo_examen')->insertGetId(
                ['nombre' => 'Regular', 'categoria' => 'REGULAR'],
                'id_tipo_examen'
            );

        return DB::table('examen')->insertGetId(array_merge([
            'nombre_examen' => 'Examen de prueba',
            'fecha' => '2026-10-01',
            'hora_inicio' => '08:00',
            'id_tipo_examen' => $typeId,
            'id_usuario_docente' => $teacherId,
        ], $overrides), 'id_examen');
    }

    private function insertGroup(string $teacherId): int
    {
        $facultyId = DB::table('facultad')->insertGetId(
            ['nombre' => 'Tecnología', 'codigo' => 'FCYT', 'estado' => 'ACTIVO'],
            'id_facultad'
        );
        $careerId = DB::table('carrera')->insertGetId(
            ['nombre' => 'Sistemas', 'codigo' => 'SIS', 'estado' => 'ACTIVO', 'id_facultad' => $facultyId],
            'id_carrera'
        );
        $subjectId = DB::table('materia')->insertGetId(
            ['nombre' => 'Cálculo', 'codigo' => 'MAT-1', 'estado' => 'ACTIVO'],
            'id_materia'
        );
        DB::table('materia_carrera')->insert(
            ['id_carrera' => $careerId, 'id_materia' => $subjectId, 'estado' => 'ACTIVO']
        );
        $yearId = DB::table('gestion')->insertGetId(['anio' => 2026, 'estado' => 'ACTIVO'], 'id_gestion');
        $typeId = (int) DB::table('periodo')->where('codigo', 1)->value('id_periodo');
        $periodId = DB::table('gestion_periodo')->insertGetId(
            ['id_gestion' => $yearId, 'id_periodo' => $typeId],
            'id_gestion_periodo'
        );

        DB::table('oferta_academica')->insert([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'id_gestion_periodo' => $periodId,
        ]);

        return DB::table('grupo')->insertGetId([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'num_grupo' => '1',
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $teacherId,
            'id_gestion_periodo' => $periodId,
        ], 'id_grupo');
    }

    private function insertStudent(string $sisCode, ?string $ci): void
    {
        DB::table('estudiante')->insert([
            'cod_sis' => $sisCode,
            'ci' => $ci,
            'nombre' => 'Nombre',
            'apellido_paterno' => 'Apellido',
            'estado' => 'ACTIVO',
        ]);
    }
}