<?php

namespace Tests\Feature\Academic;

use App\Models\Exam;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-029: GET /api/grupos/{id_grupo}/auxiliares — los auxiliares de un grupo propio y los
 * exámenes de ese grupo en que están habilitados.
 */
class GroupAssistantsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();
    }

    public function test_lista_los_auxiliares_activos_del_grupo_con_sus_datos(): void
    {
        $ana = $this->createAssistant('Ana', '201900001');
        $beto = $this->createAssistant('Beto', '201900002');
        $this->putInGroup($beto, $this->grupoPropioId);
        $this->putInGroup($ana, $this->grupoPropioId);

        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id_usuario', $ana)
            ->assertJsonPath('data.0.cod_sis', '201900001')
            ->assertJsonPath('data.0.nombre_completo', 'Ana Auxiliar')
            ->assertJsonPath('data.1.id_usuario', $beto)
            ->assertJsonStructure(['data' => [['id_usuario', 'nombre_completo', 'cod_sis', 'correo', 'fecha_incorporacion', 'examenes']]]);
    }

    public function test_no_lista_a_los_auxiliares_quitados_ni_a_los_de_otro_grupo(): void
    {
        $activo = $this->createAssistant('Activo', '201900010');
        $quitado = $this->createAssistant('Quitado', '201900011');
        $deOtroGrupo = $this->createAssistant('Otro', '201900012');
        $this->putInGroup($activo, $this->grupoPropioId);
        $this->putInGroup($quitado, $this->grupoPropioId);
        $this->putInGroup($deOtroGrupo, $this->segundoGrupoPropioId);
        DB::table('grupo_auxiliar')
            ->where('id_grupo', $this->grupoPropioId)
            ->where('id_usuario', $quitado)
            ->update(['estado' => 'INACTIVO']);

        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_usuario', $activo);
    }

    public function test_incluye_los_examenes_del_grupo_donde_esta_habilitado_y_omite_cancelados(): void
    {
        $ana = $this->createAssistant('Ana', '201900001');
        $this->putInGroup($ana, $this->grupoPropioId);

        $this->enableInExam($ana, $this->examId);
        $cancelado = $this->createExamLinkedTo([$this->grupoPropioId], Exam::CANCELADO, null, 'Cancelado');
        $this->enableInExam($ana, $cancelado);
        $deOtroGrupo = $this->createExamLinkedTo([$this->segundoGrupoPropioId], Exam::PROGRAMADO, null, 'De otro grupo');
        $this->enableInExam($ana, $deOtroGrupo);
        $this->createExamLinkedTo([$this->grupoPropioId], Exam::PROGRAMADO, null, 'Sin habilitar');

        $response = $this->getJson($this->url($this->grupoPropioId))->assertOk();

        $response->assertJsonCount(1, 'data.0.examenes')
            ->assertJsonPath('data.0.examenes.0.id_examen', $this->examId)
            ->assertJsonPath('data.0.examenes.0.nombre_examen', 'Parcial 1')
            ->assertJsonPath('data.0.examenes.0.estado', Exam::PROGRAMADO);
    }

    public function test_un_auxiliar_sin_examenes_habilitados_trae_la_lista_vacia(): void
    {
        $this->putInGroup($this->createAssistant('Ana', '201900001'), $this->grupoPropioId);

        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonPath('data.0.examenes', []);
    }

    public function test_un_grupo_sin_auxiliares_responde_data_vacio(): void
    {
        $this->getJson($this->url($this->grupoPropioId))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_el_docente_de_otro_grupo_recibe_403_y_no_ve_sus_auxiliares(): void
    {
        $this->putInGroup($this->createAssistant('Reservada', '201900099'), $this->grupoPropioId);

        $this->actAsTeacher($this->otroDocenteId);

        $response = $this->getJson($this->url($this->grupoPropioId))
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente que dicta el grupo puede ver su detalle.')
            ->assertDontSee('Reservada');

        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_un_auxiliar_recibe_403_aunque_este_en_el_grupo(): void
    {
        $auxiliar = $this->createAccount(Role::AUXILIAR);
        $this->putInGroup($auxiliar->id_usuario, $this->grupoPropioId);
        $this->actAs($auxiliar);

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
        return "/api/grupos/{$groupId}/auxiliares";
    }
}
