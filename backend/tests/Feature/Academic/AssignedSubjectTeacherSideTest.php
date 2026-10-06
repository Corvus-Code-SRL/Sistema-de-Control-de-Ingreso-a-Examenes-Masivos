<?php

namespace Tests\Feature\Academic;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Support\RecordStatus;
use Database\Seeders\ActionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * HU-006, criterio 12: un par recién asignado por el Administrador aparece en las materias del
 * docente en la siguiente carga y permite registrar un grupo en él.
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

    private function assignNewSubjectToSystems(): Subject
    {
        $subject = Subject::create([
            'nombre' => 'Inteligencia Artificial',
            'codigo' => 'INF-901',
            'estado' => RecordStatus::ACTIVE,
        ]);

        $this->actingAs($this->administrator)
            ->postJson("/api/administracion/carreras/{$this->sistemasId}/materias", [
                'id_materia' => $subject->id_materia,
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

    public function test_el_par_asignado_aparece_en_las_materias_del_docente_en_la_siguiente_carga(): void
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
        ]);

        $pair = $this->findPair($this->pairs(), $subject->id_materia);
        $this->assertTrue($pair['es_mia']);
        $this->assertSame(1, $pair['cantidad_grupos']);
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
