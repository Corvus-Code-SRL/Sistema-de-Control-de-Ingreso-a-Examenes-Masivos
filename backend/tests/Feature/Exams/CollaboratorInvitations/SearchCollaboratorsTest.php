<?php

namespace Tests\Feature\Exams\CollaboratorInvitations;

use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** HU-026, criterios 3, 4 y 5: la búsqueda de docentes de la misma materia. */
class SearchCollaboratorsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->createExam();
    }

    private function search(string $criteria, ?Exam $exam = null): \Illuminate\Testing\TestResponse
    {
        $exam = $exam ?? $this->exam;

        return $this->getJson($this->invitationsUrl($exam) . '/docentes?criterio=' . urlencode($criteria));
    }

    public function test_encuentra_por_nombre_sin_distinguir_tildes_ni_mayusculas(): void
    {
        $jose = $this->createTeacherAccount('José', 'Pérez', '200200001', $this->calculoId);

        foreach (['jose', 'JOSÉ', 'perez', 'PÉREZ', 'jose perez', 'osé pér'] as $criteria) {
            $this->search($criteria)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id_usuario', $jose);
        }
    }

    public function test_encuentra_por_codigo_sis(): void
    {
        $teacher = $this->createTeacherAccount('Marta', 'Quiroga', '200200777', $this->calculoId);

        $this->search('200200777')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_usuario', $teacher)
            ->assertJsonPath('data.0.cod_sis', '200200777')
            ->assertJsonPath('data.0.nombre_completo', 'Marta Quiroga')
            ->assertJsonPath('data.0.ya_invitado', false);
    }

    public function test_encuentra_a_un_docente_de_la_misma_materia_en_otra_carrera(): void
    {
        $teacher = $this->createTeacherAccount(
            'Rita',
            'Camacho',
            '200200002',
            $this->calculoId,
            ['carrera' => $this->informaticaId]
        );

        $this->search('camacho')->assertOk()->assertJsonPath('data.0.id_usuario', $teacher);
    }

    public function test_solo_muestra_docentes_con_grupos_de_la_materia_del_examen(): void
    {
        $this->createTeacherAccount('Pablo', 'Mendoza', '200200003', $this->basesDatosId);
        $this->createTeacherAccount('Elsa', 'Mendoza', '200200004', null);

        // Otro docente que sí dicta Cálculo II en el período vigente (Luis Vargas del catálogo).
        $this->search('mendoza')->assertOk()->assertJsonCount(0, 'data');
        $this->search('vargas')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_usuario', $this->otroDocenteId);
    }

    public function test_no_cuenta_grupos_de_periodos_anteriores_ni_inactivos(): void
    {
        $this->createTeacherAccount(
            'Gala',
            'Ortuño',
            '200200005',
            $this->calculoId,
            ['periodo' => $this->periodoAnteriorId]
        );
        $this->createTeacherAccount(
            'Hugo',
            'Ortuño',
            '200200006',
            $this->calculoId,
            ['estado_grupo' => RecordStatus::INACTIVE]
        );

        $this->search('ortuño')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_nunca_muestra_al_propio_docente(): void
    {
        // Ana Rojas dicta Cálculo II y es quien invita.
        $this->search('rojas')->assertOk()->assertJsonCount(0, 'data');
        $this->search('200100001')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_excluye_cuentas_inactivas(): void
    {
        $this->createTeacherAccount('Inés', 'Salvatierra', '200200007', $this->calculoId, [
            'estado' => RecordStatus::INACTIVE,
        ]);

        $this->search('salvatierra')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_incluye_al_docente_invitado_y_excluye_roles_que_no_ejercen_la_docencia(): void
    {
        $guest = $this->createTeacherAccount('Omar', 'Zapata', 'INV4821', $this->calculoId, ['rol' => Role::INVITADO]);
        $this->createTeacherAccount('Zoe', 'Zapata', '200200008', $this->calculoId, ['rol' => Role::AUXILIAR]);

        $this->search('zapata')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_usuario', $guest);
    }

    public function test_sin_coincidencias_devuelve_una_lista_vacia(): void
    {
        $this->search('nadie con ese nombre')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_marca_como_ya_invitado_con_invitacion_pendiente_o_aceptada(): void
    {
        $pending = $this->createTeacherAccount('Nora', 'Linares', '200200009', $this->calculoId);
        $accepted = $this->createTeacherAccount('Nilo', 'Linares', '200200010', $this->calculoId);
        $rejected = $this->createTeacherAccount('Nelly', 'Linares', '200200011', $this->calculoId);
        $void = $this->createTeacherAccount('Nando', 'Linares', '200200012', $this->calculoId);

        $this->createInvitation($this->exam, $pending);
        $this->createInvitation($this->exam, $accepted, ExamInvitation::ACEPTADA);
        $this->createInvitation($this->exam, $rejected, ExamInvitation::RECHAZADA);
        $this->createInvitation($this->exam, $void, ExamInvitation::SIN_EFECTO);

        $flags = collect($this->search('linares')->assertOk()->json('data'))
            ->pluck('ya_invitado', 'id_usuario')
            ->all();

        $this->assertEquals([
            $pending => true,
            $accepted => true,
            $rejected => false,
            $void => false,
        ], $flags);
    }

    public function test_la_invitacion_a_otro_examen_no_marca_como_ya_invitado(): void
    {
        $teacher = $this->createTeacherAccount('Olga', 'Herrera', '200200013', $this->calculoId);
        $other = $this->createExam(['nombre_examen' => 'Otro examen']);
        $this->createInvitation($other, $teacher);

        $this->search('herrera')->assertOk()->assertJsonPath('data.0.ya_invitado', false);
    }

    public function test_solo_el_creador_busca_en_su_examen(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        $this->search('vargas')->assertForbidden();
    }

    public function test_un_examen_que_ya_abrio_el_ingreso_responde_409(): void
    {
        $this->exam->update(['estado' => Exam::EN_INGRESO]);

        $this->search('vargas')
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El control de ingreso del examen ya se inició: ya no se puede invitar docentes.'
            );
    }

    public function test_un_examen_inexistente_responde_404(): void
    {
        $this->getJson('/api/docente/invitaciones/examenes/987654/docentes?criterio=vargas')->assertNotFound();
    }

    public function test_el_criterio_es_obligatorio_y_de_al_menos_dos_caracteres(): void
    {
        $this->getJson($this->invitationsUrl($this->exam) . '/docentes')
            ->assertStatus(422)->assertJsonValidationErrors('criterio');
        $this->search('a')->assertStatus(422)->assertJsonValidationErrors('criterio');
    }

    public function test_limita_los_resultados_a_veinte(): void
    {
        for ($i = 1; $i <= 22; $i++) {
            $this->createTeacherAccount("Docente{$i}", 'Masivo', sprintf('2003%05d', $i), $this->calculoId);
        }

        $this->search('masivo')->assertOk()->assertJsonCount(20, 'data');
    }
}
