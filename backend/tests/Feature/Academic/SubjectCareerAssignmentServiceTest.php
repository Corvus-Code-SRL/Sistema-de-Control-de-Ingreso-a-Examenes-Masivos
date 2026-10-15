<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicOffering;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Career;
use App\Models\Period;
use App\Models\PeriodType;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Services\Academic\SubjectCareerAssignmentService;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/** HU-006: reglas del servicio de asignación por período. */
class SubjectCareerAssignmentServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    private SubjectCareerAssignmentService $service;
    private Career $career;
    private Subject $subject;
    /** 2/2026: el período vigente, el único donde se puede escribir. */
    private int $periodId;
    /** 1/2026: historial de solo consulta. */
    private int $pastPeriodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();

        $facultyId = DB::table('facultad')->insertGetId([
            'nombre' => 'Facultad de Ciencias y Tecnologia',
            'codigo' => 'FCYT',
            'estado' => RecordStatus::ACTIVE,
        ], 'id_facultad');

        $this->career = Career::create([
            'nombre' => 'Ingenieria de Sistemas',
            'codigo' => 'SIS',
            'estado' => RecordStatus::ACTIVE,
            'id_facultad' => $facultyId,
        ]);

        $this->subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => '2008001',
            'descripcion' => 'Materia de prueba para HU-006',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->pastPeriodId = $this->createPeriod(2026, 1);
        $this->periodId = $this->createPeriod(2026, 2, RecordStatus::ACTIVE, true);

        $this->service = $this->app->make(SubjectCareerAssignmentService::class);
    }

    private function createPeriod(
        int $year,
        int $code,
        string $status = RecordStatus::ACTIVE,
        bool $current = false
    ): int {
        $yearId = AcademicYear::query()->firstOrCreate(
            ['anio' => $year],
            ['estado' => RecordStatus::ACTIVE]
        )->id_gestion;

        return Period::create([
            'id_gestion' => $yearId,
            'id_periodo' => PeriodType::query()->where('codigo', $code)->value('id_periodo'),
            'vigente' => $current,
            'estado' => $status,
        ])->id_gestion_periodo;
    }

    /** Simula el cambio de período vigente: libera el actual antes de fijar el nuevo. */
    private function makeCurrent(int $periodId): void
    {
        Period::query()->where('vigente', true)->update(['vigente' => false]);
        Period::query()->whereKey($periodId)->update(['vigente' => true]);
    }

    private function assign(?int $periodId = null): AcademicOffering
    {
        return $this->service->assign(
            $this->career,
            (int) $this->subject->id_materia,
            $periodId ?? $this->periodId,
            $this->administratorId
        );
    }

    private function assertRejected(callable $action, string $field): void
    {
        try {
            $action();

            $this->fail("Se esperaba una ValidationException sobre {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_asigna_una_materia_activa_a_una_carrera_activa_en_un_periodo(): void
    {
        $offering = $this->assign();

        $this->assertSame($this->career->id_carrera, $offering->id_carrera);
        $this->assertSame($this->subject->id_materia, $offering->id_materia);
        $this->assertSame($this->periodId, $offering->id_gestion_periodo);
        $this->assertSame(RecordStatus::ACTIVE, $offering->estado);
        $this->assertSame('2/2026', $offering->period->label);

        $this->assertDatabaseHas('materia_carrera', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);
        $this->assertDatabaseHas('oferta_academica', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'id_gestion_periodo' => $this->periodId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $pairLog = AuditLog::query()->where('tabla_afectada', 'materia_carrera')->first();
        $offeringLog = AuditLog::query()->where('tabla_afectada', 'oferta_academica')->first();

        $this->assertNotNull($pairLog);
        $this->assertNotNull($offeringLog);
        $this->assertSame($this->administratorId, $pairLog->id_usuario);
        $this->assertSame($this->administratorId, $offeringLog->id_usuario);
        $this->assertSame($this->periodId, $offeringLog->nuevo_valor['id_gestion_periodo']);
    }

    public function test_un_segundo_periodo_reutiliza_el_par_y_audita_solo_la_oferta(): void
    {
        $this->assign($this->periodId);

        $nextPeriodId = $this->createPeriod(2027, 1);
        $this->makeCurrent($nextPeriodId);
        $this->assign($nextPeriodId);

        $this->assertSame(
            1,
            SubjectCareer::query()->where('id_materia', $this->subject->id_materia)->count()
        );
        $this->assertSame(
            2,
            AcademicOffering::query()->where('id_materia', $this->subject->id_materia)->count()
        );
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'materia_carrera')->count());
        $this->assertSame(2, AuditLog::query()->where('tabla_afectada', 'oferta_academica')->count());
    }

    public function test_rechaza_una_oferta_duplicada_en_el_mismo_periodo_sin_escribir(): void
    {
        $this->assign();

        $this->assertRejected(fn () => $this->assign(), 'id_materia');

        $this->assertSame(
            1,
            AcademicOffering::query()->where('id_materia', $this->subject->id_materia)->count()
        );
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'oferta_academica')->count());
    }

    public function test_rechaza_un_par_inactivo_sin_reactivarlo_ni_ofertar(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::INACTIVE,
        ]);

        $this->assertRejected(fn () => $this->assign(), 'id_materia');

        $this->assertSame(
            RecordStatus::INACTIVE,
            SubjectCareer::query()->where('id_materia', $this->subject->id_materia)->value('estado')
        );
        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('log', ['tabla_afectada' => 'oferta_academica']);
    }

    public function test_rechaza_una_materia_inactiva(): void
    {
        $this->subject->update(['estado' => RecordStatus::INACTIVE]);

        $this->assertRejected(fn () => $this->assign(), 'id_materia');

        $this->assertDatabaseMissing('materia_carrera', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $this->subject->id_materia]);
    }

    public function test_rechaza_una_carrera_inactiva(): void
    {
        $this->career->update(['estado' => RecordStatus::INACTIVE]);

        $this->assertRejected(fn () => $this->assign(), 'id_carrera');

        $this->assertDatabaseMissing('materia_carrera', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $this->subject->id_materia]);
    }

    public function test_rechaza_un_periodo_que_no_es_el_vigente_sin_escribir_ni_auditar(): void
    {
        $this->assertRejected(fn () => $this->assign($this->pastPeriodId), 'id_periodo');

        try {
            $this->assign($this->pastPeriodId);
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Solo se pueden asignar materias en el período vigente.'],
                $exception->errors()['id_periodo']
            );
        }

        $this->assertDatabaseMissing('materia_carrera', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $this->subject->id_materia]);
        $this->assertSame(
            0,
            AuditLog::query()->whereIn('tabla_afectada', ['materia_carrera', 'oferta_academica'])->count()
        );
    }

    public function test_sin_periodo_vigente_toda_asignacion_se_rechaza(): void
    {
        Period::query()->update(['vigente' => false]);

        $this->assertRejected(fn () => $this->assign($this->periodId), 'id_periodo');
        $this->assertRejected(fn () => $this->assign($this->pastPeriodId), 'id_periodo');

        $this->assertDatabaseMissing('materia_carrera', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $this->subject->id_materia]);
    }

    public function test_la_asignacion_bloquea_la_fila_del_periodo_con_for_share(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $this->assign();

        $locks = array_filter(
            $queries,
            fn (string $sql) => str_contains($sql, '"gestion_periodo"') && str_contains($sql, 'for share')
        );

        $this->assertNotEmpty($locks);
    }

    public function test_rechaza_un_periodo_inactivo_o_inexistente(): void
    {
        $inactive = $this->createPeriod(2027, 1, RecordStatus::INACTIVE);

        $this->assertRejected(fn () => $this->assign($inactive), 'id_periodo');
        $this->assertRejected(fn () => $this->assign(999999), 'id_periodo');
        $this->assertRejected(fn () => $this->service->activePeriod($inactive), 'id_periodo');

        $this->assertDatabaseMissing('materia_carrera', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $this->subject->id_materia]);
    }

    public function test_si_falla_la_oferta_no_queda_el_par_creado(): void
    {
        $this->assertRejected(fn () => $this->assign(999999), 'id_periodo');

        $this->assertDatabaseMissing('materia_carrera', ['id_materia' => $this->subject->id_materia]);
        $this->assertDatabaseMissing('log', ['tabla_afectada' => 'materia_carrera']);
    }

    public function test_lista_unicamente_carreras_activas(): void
    {
        $inactiveCareer = Career::create([
            'nombre' => 'Carrera Archivada',
            'codigo' => 'ARC',
            'estado' => RecordStatus::INACTIVE,
            'id_facultad' => $this->career->id_facultad,
        ]);

        $careerIds = $this->service->listActiveCareers()->pluck('id_carrera')->all();

        $this->assertContains($this->career->id_carrera, $careerIds);
        $this->assertNotContains($inactiveCareer->id_carrera, $careerIds);
    }

    public function test_lista_solo_materias_activas_sin_oferta_de_la_carrera_en_el_periodo(): void
    {
        $offeredHere = Subject::create(['nombre' => 'Sistemas Operativos', 'codigo' => '2008002', 'estado' => RecordStatus::ACTIVE]);
        $offeredElsewhere = Subject::create(['nombre' => 'Redes de Computadoras', 'codigo' => '2008003', 'estado' => RecordStatus::ACTIVE]);
        $inactiveSubject = Subject::create(['nombre' => 'Materia Archivada', 'codigo' => '2008004', 'estado' => RecordStatus::INACTIVE]);
        $inactivePair = Subject::create(['nombre' => 'Compiladores', 'codigo' => '2008005', 'estado' => RecordStatus::ACTIVE]);

        foreach ([$offeredHere, $offeredElsewhere] as $subject) {
            SubjectCareer::create([
                'id_carrera' => $this->career->id_carrera,
                'id_materia' => $subject->id_materia,
                'estado' => RecordStatus::ACTIVE,
            ]);
        }
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $inactivePair->id_materia,
            'estado' => RecordStatus::INACTIVE,
        ]);

        AcademicOffering::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $offeredHere->id_materia,
            'id_gestion_periodo' => $this->periodId,
            'estado' => RecordStatus::ACTIVE,
        ]);
        AcademicOffering::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $offeredElsewhere->id_materia,
            'id_gestion_periodo' => $this->pastPeriodId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $period = $this->service->activePeriod($this->periodId);
        $subjectIds = $this->service->listAssignableSubjects($this->career, $period)->pluck('id_materia')->all();

        $this->assertContains($this->subject->id_materia, $subjectIds);
        $this->assertContains($offeredElsewhere->id_materia, $subjectIds);
        $this->assertNotContains($offeredHere->id_materia, $subjectIds);
        $this->assertNotContains($inactivePair->id_materia, $subjectIds);
        $this->assertNotContains($inactiveSubject->id_materia, $subjectIds);
    }

    public function test_las_materias_asignables_exigen_el_periodo_vigente(): void
    {
        $this->assertRejected(
            fn () => $this->service->listAssignableSubjects(
                $this->career,
                $this->service->activePeriod($this->pastPeriodId)
            ),
            'id_periodo'
        );

        Period::query()->update(['vigente' => false]);

        $this->assertRejected(
            fn () => $this->service->listAssignableSubjects(
                $this->career,
                $this->service->activePeriod($this->periodId)
            ),
            'id_periodo'
        );
    }

    public function test_las_materias_asignables_exigen_una_carrera_activa(): void
    {
        $this->career->update(['estado' => RecordStatus::INACTIVE]);

        $this->assertRejected(
            fn () => $this->service->listAssignableSubjects(
                $this->career->fresh(),
                $this->service->activePeriod($this->periodId)
            ),
            'id_carrera'
        );
    }
}
