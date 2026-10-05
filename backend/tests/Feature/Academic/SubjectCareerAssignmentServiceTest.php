<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use App\Models\Career;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Services\Academic\SubjectCareerAssignmentService;
use App\Support\RecordStatus;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class SubjectCareerAssignmentServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    private SubjectCareerAssignmentService $service;
    private Career $career;
    private Subject $subject;

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

        $this->service = $this->app->make(
            SubjectCareerAssignmentService::class
        );
    }

    public function test_asigna_una_materia_activa_a_una_carrera_activa(): void
    {
        $pair = $this->service->assign(
            $this->career,
            (int) $this->subject->id_materia,
            $this->administratorId
        );

        $this->assertSame(
            $this->career->id_carrera,
            $pair->id_carrera
        );

        $this->assertSame(
            $this->subject->id_materia,
            $pair->id_materia
        );

        $this->assertSame(
            RecordStatus::ACTIVE,
            $pair->estado
        );

        $this->assertDatabaseHas('materia_carrera', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $auditLog = AuditLog::query()
            ->where('tabla_afectada', 'materia_carrera')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame(
            $this->administratorId,
            $auditLog->id_usuario
        );

        $this->assertSame(
            $this->career->id_carrera,
            $auditLog->nuevo_valor['id_carrera']
        );

        $this->assertSame(
            $this->subject->id_materia,
            $auditLog->nuevo_valor['id_materia']
        );
    }

    public function test_rechaza_un_par_materia_carrera_duplicado(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        try {
            $this->service->assign(
                $this->career,
                (int) $this->subject->id_materia,
                $this->administratorId
            );

            $this->fail('Se esperaba una ValidationException por asignación duplicada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id_materia', $exception->errors());
        }

        $this->assertSame(
            1,
            SubjectCareer::query()
                ->where('id_carrera', $this->career->id_carrera)
                ->where('id_materia', $this->subject->id_materia)
                ->count()
        );

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }

    public function test_rechaza_una_materia_inactiva(): void
    {
        $this->subject->update([
            'estado' => RecordStatus::INACTIVE,
        ]);

        try {
            $this->service->assign(
                $this->career,
                (int) $this->subject->id_materia,
                $this->administratorId
            );

            $this->fail('Se esperaba una ValidationException por materia inactiva.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id_materia', $exception->errors());
        }

        $this->assertDatabaseMissing('materia_carrera', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
        ]);

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }

    public function test_rechaza_una_carrera_inactiva(): void
    {
        $this->career->update([
            'estado' => RecordStatus::INACTIVE,
        ]);

        try {
            $this->service->assign(
                $this->career,
                (int) $this->subject->id_materia,
                $this->administratorId
            );

            $this->fail('Se esperaba una ValidationException por carrera inactiva.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id_carrera', $exception->errors());
        }

        $this->assertDatabaseMissing('materia_carrera', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
        ]);

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }

    public function test_lista_unicamente_carreras_activas(): void
    {
        $inactiveCareer = Career::create([
            'nombre' => 'Carrera Archivada',
            'codigo' => 'ARC',
            'estado' => RecordStatus::INACTIVE,
            'id_facultad' => $this->career->id_facultad,
        ]);

        $careerIds = $this->service
            ->listActiveCareers()
            ->pluck('id_carrera')
            ->all();

        $this->assertContains($this->career->id_carrera, $careerIds);
        $this->assertNotContains($inactiveCareer->id_carrera, $careerIds);
    }

    public function test_lista_solo_materias_activas_sin_par_con_la_carrera(): void
    {
        $assignedActivePair = Subject::create([
            'nombre' => 'Sistemas Operativos',
            'codigo' => '2008002',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $assignedInactivePair = Subject::create([
            'nombre' => 'Redes de Computadoras',
            'codigo' => '2008003',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $inactiveSubject = Subject::create([
            'nombre' => 'Materia Archivada',
            'codigo' => '2008004',
            'estado' => RecordStatus::INACTIVE,
        ]);

        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $assignedActivePair->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $assignedInactivePair->id_materia,
            'estado' => RecordStatus::INACTIVE,
        ]);

        $subjectIds = $this->service
            ->listAssignableSubjects($this->career)
            ->pluck('id_materia')
            ->all();

        $this->assertContains($this->subject->id_materia, $subjectIds);

        $this->assertNotContains(
            $assignedActivePair->id_materia,
            $subjectIds
        );

        $this->assertNotContains(
            $assignedInactivePair->id_materia,
            $subjectIds
        );

        $this->assertNotContains(
            $inactiveSubject->id_materia,
            $subjectIds
        );
    }
}