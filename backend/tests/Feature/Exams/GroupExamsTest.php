<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-029: GET /api/grupos/{id_grupo}/examenes — los exámenes que incluyen un grupo propio.
 */
class GroupExamsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_lista_solo_los_examenes_que_incluyen_el_grupo_ordenados_por_fecha(): void
    {
        $lejano = $this->examOfGroup($this->grupoPropioId, ['nombre_examen' => 'Lejano', 'fecha' => $this->futureDate(20)]);
        $cercano = $this->examOfGroup($this->grupoPropioId, ['nombre_examen' => 'Cercano', 'fecha' => $this->futureDate(5)]);
        $this->examOfGroup($this->segundoGrupoId(), ['nombre_examen' => 'Del otro grupo']);
        $this->createExam(['nombre_examen' => 'Sin grupos']);

        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id_examen', $cercano->id_examen)
            ->assertJsonPath('data.1.id_examen', $lejano->id_examen)
            ->assertJsonPath('data.0.materia.nombre', 'Calculo II')
            ->assertJsonPath('data.0.estado', Exam::PROGRAMADO)
            ->assertJsonStructure(['data' => [['id_examen', 'nombre_examen', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'materia']]]);
    }

    public function test_incluye_los_examenes_de_cualquier_estado(): void
    {
        $this->examOfGroup($this->grupoPropioId, ['estado' => Exam::CANCELADO, 'fecha' => $this->futureDate(1)]);
        $this->examOfGroup($this->grupoPropioId, ['estado' => Exam::EN_INGRESO, 'fecha' => $this->futureDate(2)]);

        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonPath('data.0.estado', Exam::CANCELADO)
            ->assertJsonPath('data.1.estado', Exam::EN_INGRESO);
    }

    public function test_un_grupo_sin_examenes_responde_data_vacio(): void
    {
        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_el_docente_de_otro_grupo_recibe_403_y_no_ve_sus_examenes(): void
    {
        $this->examOfGroup($this->grupoPropioId, ['nombre_examen' => 'Reservado']);

        $this->actAsTeacher($this->otroDocenteId);

        $response = $this->getJson($this->url($this->grupoPropioId))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente que dicta el grupo puede ver su detalle.')
            ->assertDontSee('Reservado');

        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $this->getJson($this->url($this->grupoPropioId))->assertForbidden();
    }

    public function test_un_administrador_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson($this->url($this->grupoPropioId))->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson($this->url($this->grupoPropioId))->assertUnauthorized();
    }

    public function test_responde_404_cuando_el_grupo_no_existe(): void
    {
        $this->getJson($this->url(999999))
            ->assertNotFound()
            ->assertJsonPath('message', 'No existe el grupo indicado.');
    }

    public function test_el_id_maximo_valido_responde_404_y_no_500(): void
    {
        $this->getJson($this->url(2147483647))->assertNotFound();
    }

    /** @dataProvider invalidIdentifiers */
    public function test_rechaza_con_422_un_id_de_grupo_invalido(string $id): void
    {
        $this->getJson($this->url($id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');
    }

    /** @return array<string, array{0: string}> */
    public function invalidIdentifiers(): array
    {
        return [
            'no numérico' => ['abc'],
            'cero' => ['0'],
            'negativo' => ['-5'],
            'sobre el rango de int4' => ['2147483648'],
        ];
    }

    private function url($groupId): string
    {
        return "/api/grupos/{$groupId}/examenes";
    }

    private function segundoGrupoId(): int
    {
        return (int) DB::table('grupo')
            ->where('id_usuario_docente', $this->docenteId)
            ->where('num_grupo', '2')
            ->value('id_grupo');
    }

    /** Examen del docente vinculado al grupo indicado. */
    private function examOfGroup(int $groupId, array $overrides = []): Exam
    {
        $exam = $this->createExam($overrides);

        DB::table('grupo_examen')->insert(['id_grupo' => $groupId, 'id_examen' => $exam->id_examen]);

        return $exam;
    }
}
