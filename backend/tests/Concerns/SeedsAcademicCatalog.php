<?php

namespace Tests\Concerns;

use App\Models\AcademicYear;
use App\Models\Career;
use App\Models\Group;
use App\Models\Period;
use App\Models\PeriodType;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Support\RecordStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de prueba fijos para el catálogo académico.
 *
 * No se usan factories porque el esquema no lo definen migraciones sino el script de
 * creación: las relaciones de este módulo (par materia-carrera, oferta académica, grupos por
 * período) se leen mejor con un juego de datos explícito.
 *
 * El período vigente es 1/2026 (gestion_periodo.vigente) y el anterior 2/2025. Todo par creado aquí
 * queda ofertado en el período vigente; cada grupo oferta su par en el período del grupo.
 */
trait SeedsAcademicCatalog
{
    protected string $docenteId = '11111111-1111-4111-8111-111111111111';

    protected string $otroDocenteId = '22222222-2222-4222-8222-222222222222';

    protected int $facultadId;

    protected int $sistemasId;

    protected int $informaticaId;

    protected int $calculoId;

    protected int $basesDatosId;

    protected int $materiaInactivaId;

    protected int $periodoActivoId;

    protected int $periodoAnteriorId;

    protected int $grupoPropioId;

    protected int $grupoAjenoId;

    protected int $grupoPeriodoAnteriorId;

    protected int $grupoParInactivoId;

    protected function seedAcademicCatalog(): void
    {
        $this->seedTeachers();

        $this->facultadId = DB::table('facultad')->insertGetId([
            'nombre' => 'Ciencias y Tecnologia',
            'codigo' => 'FCYT',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_facultad');

        $this->sistemasId = Career::create([
            'nombre' => 'Ingenieria de Sistemas',
            'codigo' => 'SIS',
            'estado' => RecordStatus::ACTIVE,
            'id_facultad' => $this->facultadId,
        ])->id_carrera;

        $this->informaticaId = Career::create([
            'nombre' => 'Ingenieria Informatica',
            'codigo' => 'INF',
            'estado' => RecordStatus::ACTIVE,
            'id_facultad' => $this->facultadId,
        ])->id_carrera;

        $this->calculoId = Subject::create([
            'nombre' => 'Calculo II',
            'codigo' => 'MAT-102',
            'descripcion' => 'Calculo diferencial e integral',
            'estado' => RecordStatus::ACTIVE,
        ])->id_materia;

        $this->basesDatosId = Subject::create([
            'nombre' => 'Base de Datos I',
            'codigo' => 'INF-201',
            'estado' => RecordStatus::ACTIVE,
        ])->id_materia;

        $this->materiaInactivaId = Subject::create([
            'nombre' => 'Materia Archivada',
            'codigo' => 'OLD-001',
            'estado' => RecordStatus::INACTIVE,
        ])->id_materia;

        // Calculo II existe en dos carreras: es el caso que obliga a trabajar por par.
        $this->createPair($this->sistemasId, $this->calculoId, RecordStatus::ACTIVE);
        $this->createPair($this->informaticaId, $this->calculoId, RecordStatus::ACTIVE);
        $this->createPair($this->sistemasId, $this->basesDatosId, RecordStatus::ACTIVE);

        // Materia INACTIVA con par ACTIVO.
        $this->createPair($this->sistemasId, $this->materiaInactivaId, RecordStatus::ACTIVE);

        // Materia ACTIVA con par INACTIVO.
        $this->createPair($this->informaticaId, $this->basesDatosId, RecordStatus::INACTIVE);

        $this->periodoActivoId = $this->createPeriod(2026, 1, true);
        $this->periodoAnteriorId = $this->createPeriod(2025, 2, false);

        // Todo el catálogo de arriba se dicta en el período activo; los casos de «no ofertado»
        // los prepara cada prueba con dontOfferPair().
        foreach (DB::table('materia_carrera')->get(['id_carrera', 'id_materia']) as $pair) {
            $this->offerPair((int) $pair->id_carrera, (int) $pair->id_materia, $this->periodoActivoId);
        }

        $this->seedGroups();

        $this->actAsTeacher($this->docenteId);
    }

    /** Crea la gestión (si no existe) y su período; `$code` es periodo.codigo (1, 2, 3 Verano...). */
    protected function createPeriod(int $year, int $code, bool $current = false): int
    {
        $yearId = AcademicYear::query()->firstOrCreate(
            ['anio' => $year],
            ['estado' => RecordStatus::ACTIVE]
        )->id_gestion;

        return Period::create([
            'id_gestion' => $yearId,
            'id_periodo' => PeriodType::query()->where('codigo', $code)->value('id_periodo'),
            'vigente' => $current,
            'estado' => RecordStatus::ACTIVE,
        ])->id_gestion_periodo;
    }

    /** Oferta el par en el período (idempotente). */
    protected function offerPair(
        int $careerId,
        int $subjectId,
        int $periodId,
        string $status = RecordStatus::ACTIVE
    ): void {
        DB::table('oferta_academica')->insertOrIgnore([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'id_gestion_periodo' => $periodId,
            'estado' => $status,
        ]);
    }

    /** Quita la oferta del par en el período; falla si algún grupo todavía la usa. */
    protected function dontOfferPair(int $careerId, int $subjectId, int $periodId): void
    {
        DB::table('oferta_academica')
            ->where('id_carrera', $careerId)
            ->where('id_materia', $subjectId)
            ->where('id_gestion_periodo', $periodId)
            ->delete();
    }

    protected function seedExtraPairs(int $amount): void
    {
        for ($i = 1; $i <= $amount; $i++) {
            $subjectId = Subject::create([
                'nombre' => "Materia de relleno {$i}",
                'codigo' => "REL-{$i}",
                'estado' => RecordStatus::ACTIVE,
            ])->id_materia;

            $this->createPair($this->sistemasId, $subjectId, RecordStatus::ACTIVE);
            $this->offerPair($this->sistemasId, $subjectId, $this->periodoActivoId);
        }
    }

    private function seedGroups(): void
    {
        // Dos grupos propios en (Sistemas, Calculo II): el par es "mi materia".
        $this->grupoPropioId = $this->createGroup(
            $this->sistemasId,
            $this->calculoId,
            '1',
            $this->docenteId,
            $this->periodoActivoId
        );
        $this->createGroup($this->sistemasId, $this->calculoId, '2', $this->docenteId, $this->periodoActivoId);

        // Grupo de otro docente en el mismo par.
        $this->grupoAjenoId = $this->createGroup(
            $this->sistemasId,
            $this->calculoId,
            '3',
            $this->otroDocenteId,
            $this->periodoActivoId
        );

        // Grupo propio pero de un periodo anterior: no debe contar como "mi materia".
        $this->grupoPeriodoAnteriorId = $this->createGroup(
            $this->informaticaId,
            $this->calculoId,
            '1',
            $this->docenteId,
            $this->periodoAnteriorId
        );

        // Par donde el docente no dicta ningun grupo.
        $this->createGroup($this->sistemasId, $this->basesDatosId, '1', $this->otroDocenteId, $this->periodoActivoId);

        // Grupo de otro docente en un par INACTIVO: existe, pero no es contexto de trabajo.
        $this->grupoParInactivoId = $this->createGroup(
            $this->informaticaId,
            $this->basesDatosId,
            '1',
            $this->otroDocenteId,
            $this->periodoActivoId
        );
    }

    /**
     * Inscribe estudiantes en el grupo. La nómina no tiene estados: toda inscripción se
     * escribe ACTIVO y nunca se filtra por él.
     */
    protected function enrollStudents(int $groupId, int $count): void
    {
        $alreadyEnrolled = DB::table('grupo_estudiante')->where('id_grupo', $groupId)->count();

        for ($i = $alreadyEnrolled + 1; $i <= $alreadyEnrolled + $count; $i++) {
            $year = 2000 + intdiv($groupId - 1, 12);
            $month = (($groupId - 1) % 12) + 1;
            $code = sprintf('%04d%02d%03d', $year, $month, $i);

            $studentId = DB::table('estudiante')->insertGetId([
                'cod_sis' => $code,
                'ci' => $code,
                'nombre' => "Estudiante {$i}",
                'apellido_paterno' => 'Prueba',
                'estado' => RecordStatus::ACTIVE,
            ], 'id_estudiante');

            DB::table('grupo_estudiante')->insert([
                'id_grupo' => $groupId,
                'id_estudiante' => $studentId,
                'fecha_inscripcion' => '2026-02-15',
                'estado' => RecordStatus::ACTIVE,
            ]);
        }
    }

    private function seedTeachers(): void
    {
        $this->seed(RoleSeeder::class);

        DB::table('usuario')->insert([
            [
                'id_usuario' => $this->docenteId,
                'nombre' => 'Ana',
                'apellido_paterno' => 'Rojas',
                'correo' => 'ana.rojas@umss.edu',
                'contrasenia' => 'x',
                'cod_sis' => '200100001',
                'estado' => RecordStatus::ACTIVE,
            ],
            [
                'id_usuario' => $this->otroDocenteId,
                'nombre' => 'Luis',
                'apellido_paterno' => 'Vargas',
                'correo' => 'luis.vargas@umss.edu',
                'contrasenia' => 'x',
                'cod_sis' => '200100002',
                'estado' => RecordStatus::ACTIVE,
            ],
        ]);

        // Los endpoints de docente exigen el rol Docente vigente.
        $teacherRoleId = Role::where('nombre_rol', Role::DOCENTE)->value('id_rol');

        foreach ([$this->docenteId, $this->otroDocenteId] as $teacherId) {
            DB::table('usuario_rol')->insert([
                'id_usuario' => $teacherId,
                'id_rol' => $teacherRoleId,
                'fecha_inicio' => '2026-01-10 08:00:00',
            ]);
        }
    }

    private function createPair(int $careerId, int $subjectId, string $status): void
    {
        SubjectCareer::create([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'nivel_semestre' => '3',
            'obligatoria' => true,
            'estado' => $status,
        ]);
    }

    private function createGroup(
        int $careerId,
        int $subjectId,
        string $number,
        string $teacherId,
        int $periodId
    ): int {
        // Un grupo solo existe para un par ofertado en su período.
        $this->offerPair($careerId, $subjectId, $periodId);

        return Group::create([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'num_grupo' => $number,
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $teacherId,
            'id_gestion_periodo' => $periodId,
        ])->id_grupo;
    }
}
