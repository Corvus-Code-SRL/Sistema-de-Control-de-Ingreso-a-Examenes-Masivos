<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use App\Models\Career;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Models\SubjectCareer;
use App\Support\RecordStatus;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectCareerAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;
    private User $teacherUser;
    private Career $career;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            ActionSeeder::class,
        ]);

        $adminRole = Role::where(
            'nombre_rol',
            Role::ADMINISTRADOR
        )->firstOrFail();

        $teacherRole = Role::where(
            'nombre_rol',
            Role::DOCENTE
        )->firstOrFail();

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach(
            $adminRole->id_rol,
            ['fecha_inicio' => now()]
        );

        $this->teacherUser = User::factory()->create();
        $this->teacherUser->roles()->attach(
            $teacherRole->id_rol,
            ['fecha_inicio' => now()]
        );

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
            'descripcion' => 'Materia para HU-006',
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    private function assignmentUrl(): string
    {
        return "/api/administracion/carreras/{$this->career->id_carrera}/materias";
    }

    public function test_administrador_puede_asignar_materia_a_carrera(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => $this->subject->id_materia,
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.id_carrera',
                $this->career->id_carrera
            )
            ->assertJsonPath(
                'data.id_materia',
                $this->subject->id_materia
            )
            ->assertJsonPath(
                'data.estado',
                RecordStatus::ACTIVE
            )
            ->assertJsonPath(
                'mensaje',
                'Materia asignada a la carrera correctamente.'
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
            $this->adminUser->id_usuario,
            $auditLog->id_usuario
        );
    }

    public function test_docente_no_puede_asignar_materia_a_carrera(): void
    {
        $this->actingAs($this->teacherUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => $this->subject->id_materia,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('materia_carrera', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
        ]);

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }

    public function test_rechaza_asignacion_sin_materia(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }

    public function test_rechaza_asignacion_con_materia_inexistente(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }

    public function test_rechaza_asignacion_duplicada(): void
    {
        SubjectCareer::create([
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => $this->subject->id_materia,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

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

    public function test_rechaza_asignacion_si_materia_o_carrera_estan_inactivas(): void
    {
        $this->subject->update([
            'estado' => RecordStatus::INACTIVE,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => $this->subject->id_materia,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->subject->update([
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->career->update([
            'estado' => RecordStatus::INACTIVE,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => $this->subject->id_materia,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_carrera');

        $this->assertDatabaseMissing('materia_carrera', [
            'id_carrera' => $this->career->id_carrera,
            'id_materia' => $this->subject->id_materia,
        ]);

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }

    public function test_administrador_lista_unicamente_carreras_activas(): void
    {
        $inactiveCareer = Career::create([
            'nombre' => 'Carrera Archivada',
            'codigo' => 'ARC',
            'estado' => RecordStatus::INACTIVE,
            'id_facultad' => $this->career->id_facultad,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/administracion/carreras')
            ->assertOk();

        $careerIds = collect($response->json('data'))
            ->pluck('id_carrera')
            ->all();

        $this->assertContains($this->career->id_carrera, $careerIds);
        $this->assertNotContains($inactiveCareer->id_carrera, $careerIds);
    }

    public function test_administrador_lista_solo_materias_asignables(): void
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

        $response = $this->actingAs($this->adminUser)
            ->getJson(
                "/api/administracion/carreras/{$this->career->id_carrera}/materias-asignables"
            )
            ->assertOk();

        $subjectIds = collect($response->json('data'))
            ->pluck('id_materia')
            ->all();

        $this->assertContains($this->subject->id_materia, $subjectIds);
        $this->assertNotContains($assignedActivePair->id_materia, $subjectIds);
        $this->assertNotContains($assignedInactivePair->id_materia, $subjectIds);
        $this->assertNotContains($inactiveSubject->id_materia, $subjectIds);
    }

    public function test_docente_no_puede_listar_carreras_administrativas(): void
    {
        $this->actingAs($this->teacherUser)
            ->getJson('/api/administracion/carreras')
            ->assertForbidden();
    }

    public function test_docente_no_puede_listar_materias_asignables(): void
    {
        $this->actingAs($this->teacherUser)
            ->getJson(
                "/api/administracion/carreras/{$this->career->id_carrera}/materias-asignables"
            )
            ->assertForbidden();
    }

    public function test_rechaza_carrera_no_numerica_con_404(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/administracion/carreras/abc/materias', [
                'id_materia' => $this->subject->id_materia,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('materia_carrera', [
            'id_materia' => $this->subject->id_materia,
        ]);
    }

    public function test_rechaza_carrera_fuera_de_rango_con_404(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(
                '/api/administracion/carreras/99999999999/materias-asignables'
            )
            ->assertNotFound();
    }

    public function test_rechaza_id_materia_fuera_de_rango(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson($this->assignmentUrl(), [
                'id_materia' => 99999999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertDatabaseMissing('log', [
            'tabla_afectada' => 'materia_carrera',
        ]);
    }
}
