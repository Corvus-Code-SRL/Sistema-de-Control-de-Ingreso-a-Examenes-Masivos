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

    public function testPeriodGestionIsASmallintBetween1900And2200(): void
    {
        $type = DB::selectOne(
            "select data_type from information_schema.columns
             where table_schema = 'public' and table_name = 'periodo' and column_name = 'gestion'"
        );
        $this->assertSame('smallint', $type->data_type);

        DB::table('periodo')->insert(['nombre_periodo' => '1-2026', 'gestion' => 2026]);

        $this->assertRejected(
            fn () => DB::table('periodo')->insert(['nombre_periodo' => '1-1899', 'gestion' => 1899])
        );
        $this->assertRejected(
            fn () => DB::table('periodo')->insert(['nombre_periodo' => '1-2201', 'gestion' => 2201])
        );
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

    public function testBaselineMigrationSchemaIsIdenticalToTheCreationScript(): void
    {
        $normalize = fn (string $path) => str_replace("\r\n", "\n", file_get_contents($path));

        $this->assertSame(
            $normalize(base_path('../docs/database/creation-script.sql')),
            $normalize(database_path('schema/baseline.sql')),
            'database/schema/baseline.sql debe ser copia exacta de docs/database/creation-script.sql.'
        );
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
        $periodId = DB::table('periodo')->insertGetId(['nombre_periodo' => '1-2026', 'gestion' => 2026], 'id_periodo');

        return DB::table('grupo')->insertGetId([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'num_grupo' => '1',
            'gestion' => '2026',
            'estado' => 'ACTIVO',
            'id_usuario_docente' => $teacherId,
            'id_periodo' => $periodId,
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