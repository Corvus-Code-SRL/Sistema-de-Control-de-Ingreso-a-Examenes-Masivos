<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicOffering;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Models\User;
use App\Support\RecordStatus;
use Database\Seeders\ActionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * HU-006: un par asignado por el Administrador en el período vigente aparece en las materias del
 * docente en la siguiente carga y permite registrar un grupo en él. Lo ofrecido solo en otro período
 * (que ya no se escribe por la API) el docente no lo ve: el catálogo es permanente, la oferta es por
 * período.
 */
class AssignedSubjectTeacherSideTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
        $this->seed(ActionSeeder::class);

        $this->administrator = User::factory()->create();
        $this->administrator->roles()->attach(
            Role::where('nombre_rol', Role::ADMINISTRADOR)->value('id_rol'),
            ['fecha_inicio' => now()]
        );
    }

    private function assignNewSubjectToSystems(?int $periodId = null): Subject
    {
        $subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => 'INF-901',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actingAs($this->administrator)
            ->postJson("/api/administracion/carreras/{$this->sistemasId}/materias", [
                'id_materia' => $subject->id_materia,
                'id_periodo' => $periodId ?? $this->periodoActivoId,
            ])
            ->assertCreated();

        return $subject;
    }

    private function pairs(): array
    {
        return $this->getJson('/api/materias')->assertOk()->json('data');
    }

    private function findPair(array $pairs, int $subjectId): ?array
    {
        foreach ($pairs as $pair) {
            if ($pair['id_materia'] === $subjectId && $pair['id_carrera'] === $this->sistemasId) {
                return $pair;
            }
        }

        return null;
    }

    public function test_el_par_asignado_en_el_periodo_vigente_aparece_en_las_materias_del_docente(): void
    {
        $this->actAsTeacher($this->docenteId);
        $subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => 'INF-901',
            'estado' => RecordStatus::ACTIVE,
        ]);
        $this->assertNull($this->findPair($this->pairs(), $subject->id_materia));

        $this->actingAs($this->administrator)
            ->postJson("/api/administracion/carreras/{$this->sistemasId}/materias", [
                'id_materia' => $subject->id_materia,
                'id_periodo' => $this->periodoActivoId,
            ])
            ->assertCreated();

        $this->actAsTeacher($this->docenteId);
        $pair = $this->findPair($this->pairs(), $subject->id_materia);

        $this->assertNotNull($pair);
        $this->assertTrue($pair['activa']);
        $this->assertSame('Inteligencia Artificial', $pair['nombre']);
        $this->assertSame(0, $pair['cantidad_grupos']);
    }

    public function test_el_docente_puede_registrar_un_grupo_en_el_par_recien_asignado(): void
    {
        $subject = $this->assignNewSubjectToSystems();

        $this->actAsTeacher($this->docenteId);

        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $subject->id_materia,
            'num_grupo' => 'A',
        ])->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $subject->id_materia,
            'num_grupo' => 'A',
            'id_usuario_docente' => $this->docenteId,
            'id_gestion_periodo' => $this->periodoActivoId,
        ]);

        $pair = $this->findPair($this->pairs(), $subject->id_materia);
        $this->assertTrue($pair['es_mia']);
        $this->assertSame(1, $pair['cantidad_grupos']);
    }

    public function test_ofrecido_solo_en_un_periodo_pasado_el_docente_no_lo_ve_ni_registra_grupos_en_el_vigente(): void
    {
        $subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => 'INF-901',
            'estado' => RecordStatus::ACTIVE,
        ]);

        // El historial no se escribe por la API: la oferta anterior ya existía.
        SubjectCareer::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $subject->id_materia,
            'estado' => RecordStatus::ACTIVE,
        ]);
        AcademicOffering::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $subject->id_materia,
            'id_gestion_periodo' => $this->periodoAnteriorId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actAsTeacher($this->docenteId);
        $this->assertNull($this->findPair($this->pairs(), $subject->id_materia));

        $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $subject->id_materia,
            'num_grupo' => 'A',
        ])->assertStatus(422)->assertJsonValidationErrors('id_periodo');

        $this->assertDatabaseMissing('grupo', ['id_materia' => $subject->id_materia]);
    }

    public function test_el_administrador_no_puede_asignar_en_el_periodo_anterior_y_el_docente_no_ve_cambios(): void
    {
        $subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => 'INF-901',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actingAs($this->administrator)
            ->postJson("/api/administracion/carreras/{$this->sistemasId}/materias", [
                'id_materia' => $subject->id_materia,
                'id_periodo' => $this->periodoAnteriorId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');

        $this->assertDatabaseMissing('oferta_academica', ['id_materia' => $subject->id_materia]);

        $this->actAsTeacher($this->docenteId);
        $this->assertNull($this->findPair($this->pairs(), $subject->id_materia));
    }

    public function test_sin_la_asignacion_el_docente_no_puede_registrar_grupos_en_la_materia(): void
    {
        $subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => 'INF-901',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actAsTeacher($this->docenteId);

        $response = $this->postJson('/api/grupos', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $subject->id_materia,
            'num_grupo' => 'A',
        ]);

        $this->assertContains($response->getStatusCode(), [404, 422]);
        $this->assertDatabaseMissing('grupo', ['id_materia' => $subject->id_materia]);
    }
}
