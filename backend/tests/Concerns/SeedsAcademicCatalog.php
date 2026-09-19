<?php

namespace Tests\Concerns;

use App\Models\Career;
use App\Models\Group;
use App\Models\Period;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Support\RecordStatus;
use Illuminate\Support\Facades\DB;

/**
 * Datos de prueba fijos para el catálogo académico.
 *
 * No se usan factories porque el esquema no lo definen migraciones sino el script de
 * creación: las relaciones de este módulo (par materia-carrera, grupos por periodo)
 * se leen mejor con un juego de datos explícito.
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

        $this->periodoActivoId = Period::create([
            'nombre_periodo' => '2026-1',
            'gestion' => 2026,
        ])->id_periodo;

        $this->periodoAnteriorId = Period::create([
            'nombre_periodo' => '2025-2',
            'gestion' => 2025,
        ])->id_periodo;

        $this->seedGroups();

        config()->set('sciem.docente_fijo_id', $this->docenteId);
        config()->set('sciem.periodo_activo_id', $this->periodoActivoId);
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
        }
    }

    private function seedGroups(): void
    {
        // Dos grupos propios en (Sistemas, Calculo II): el par es "mi materia".
        $this->createGroup($this->sistemasId, $this->calculoId, '1', $this->docenteId, $this->periodoActivoId);
        $this->createGroup($this->sistemasId, $this->calculoId, '2', $this->docenteId, $this->periodoActivoId);

        // Grupo de otro docente en el mismo par.
        $this->createGroup($this->sistemasId, $this->calculoId, '3', $this->otroDocenteId, $this->periodoActivoId);

        // Grupo propio pero de un periodo anterior: no debe contar como "mi materia".
        $this->createGroup($this->informaticaId, $this->calculoId, '1', $this->docenteId, $this->periodoAnteriorId);

        // Par donde el docente no dicta ningun grupo.
        $this->createGroup($this->sistemasId, $this->basesDatosId, '1', $this->otroDocenteId, $this->periodoActivoId);
    }

    private function seedTeachers(): void
    {
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
    ): void {
        Group::create([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'num_grupo' => $number,
            'gestion' => '2026',
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $teacherId,
            'id_periodo' => $periodId,
        ]);
    }
}
