<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\LateEntryException;
use App\Models\Exam;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\VerificationService;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * HU-037, criterios 2 a 5 y 11: POST .../retrasos/verificar responde como el control de
 * ingreso de HU-032, con la solicitud pendiente del estudiante si la hay.
 */
class LateEntryVerificationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->actAsTeacher($this->docenteId);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, string|int|null> $overrides */
    private function verify(int $studentId, array $overrides = [])
    {
        return $this->postJson($this->lateEntryUrl('verificar'), array_merge([
            'cod_sis' => $this->studentColumn($studentId, 'cod_sis'),
            'ci' => $this->studentColumn($studentId, 'ci'),
        ], $overrides));
    }

    public function test_muestra_datos_foto_y_antecedentes_del_estudiante_esperado(): void
    {
        DB::table('estudiante')
            ->where('id_estudiante', $this->secondStudentId)
            ->update(['foto_url' => 'https://fotos.test/estudiante.webp']);

        $this->verify($this->secondStudentId)
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::AUTHORIZED)
            ->assertJsonPath('data.autorizado', true)
            ->assertJsonPath('data.estudiante.id_estudiante', $this->secondStudentId)
            ->assertJsonPath('data.estudiante.cod_sis', $this->studentColumn($this->secondStudentId, 'cod_sis'))
            ->assertJsonPath('data.estudiante.foto_url', 'https://fotos.test/estudiante.webp')
            ->assertJsonPath('data.antecedentes.total', 0)
            ->assertJsonPath('data.ambiente_asignado.nro_aula', '692B')
            ->assertJsonPath('data.solicitud_pendiente', null)
            ->assertJsonStructure(['data' => ['estudiante' => ['nombre_completo'], 'grupo', 'ingreso_previo']]);
    }

    public function test_incluye_la_solicitud_pendiente_del_estudiante(): void
    {
        $this->travelToLocal('10:12');
        $this->actAsUserId($this->assistantBId);
        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])->assertCreated();

        $this->actAsTeacher($this->docenteId);
        $this->verify($this->secondStudentId)
            ->assertOk()
            ->assertJsonPath('data.solicitud_pendiente.hora_solicitud', '10:12')
            ->assertJsonPath('data.solicitud_pendiente.ambiente.nro_aula', '692B')
            ->assertJsonPath('data.solicitud_pendiente.solicitante.nombre_completo', 'Auxiliar 202498002');
    }

    public function test_ci_distinto_no_coincide(): void
    {
        $this->verify($this->secondStudentId, ['ci' => '1'])
            ->assertOk()
            ->assertJsonPath('data.autorizado', false)
            ->assertJsonPath('data.veredicto', VerificationService::CI_MISMATCH)
            ->assertJsonPath('data.motivo', VerificationService::CI_MISMATCH_MESSAGE);
    }

    public function test_estudiante_de_otra_materia_no_pertenece(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);
        $outsiderId = (int) DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoAjenoId)
            ->value('id_estudiante');

        $this->verify($outsiderId)
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::NOT_IN_EXAM)
            ->assertJsonPath('data.motivo', VerificationService::NOT_IN_EXAM_MESSAGE)
            ->assertJsonPath('data.grupo', null);
    }

    public function test_estudiante_que_ya_ingreso_indica_hora_y_ambiente(): void
    {
        $this->insertEntry($this->firstStudentId, '10:01:00', $this->aulaId);

        $this->verify($this->firstStudentId)
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::DUPLICATE)
            ->assertJsonPath('data.motivo', 'Ya ingresó a las 10:01 por el 691A');
    }

    public function test_sis_desconocido_no_encontrado(): void
    {
        $this->postJson($this->lateEntryUrl('verificar'), ['cod_sis' => '999999999'])
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::NOT_FOUND)
            ->assertJsonPath('data.estudiante', null);
    }

    public function test_el_auxiliar_verifica_en_su_ambiente(): void
    {
        $this->actAsUserId($this->assistantBId);

        $this->verify($this->secondStudentId, ['id_ambiente' => $this->otraAulaId])
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::AUTHORIZED);
        $this->verify($this->firstStudentId, ['id_ambiente' => $this->otraAulaId])
            ->assertOk()
            ->assertJsonPath('data.veredicto', VerificationService::WRONG_ROOM);
        $this->verify($this->secondStudentId, ['id_ambiente' => $this->aulaId])
            ->assertForbidden()
            ->assertJsonPath('message', EntryControlSnapshotService::FOREIGN_ROOM_MESSAGE);
    }

    public function test_un_examen_finalizado_responde_409(): void
    {
        $this->setLateEntryExamState(Exam::FINALIZADO);

        $this->verify($this->secondStudentId)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', LateEntryException::EXAM_NOT_IN_PROGRESS)
            ->assertJsonPath('message', LateEntryException::EXAM_NOT_IN_PROGRESS_MESSAGE);
    }

    public function test_exige_sesion_y_una_cuenta_que_controle_el_examen(): void
    {
        $this->actAsTeacher($this->otroDocenteId);
        $this->verify($this->secondStudentId)->assertForbidden();

        DB::table('usuario')->insert([
            'id_usuario' => '55555555-5555-4555-8555-0000000000ff',
            'nombre' => 'Sin',
            'apellido_paterno' => 'Rol',
            'correo' => 'sin.rol@umss.edu',
            'contrasenia' => 'x',
            'cod_sis' => '202498099',
            'estado' => RecordStatus::ACTIVE,
        ]);
        $this->actAsUserId('55555555-5555-4555-8555-0000000000ff');
        $this->verify($this->secondStudentId)->assertForbidden();

        $this->actAsGuest();
        $this->verify($this->secondStudentId)->assertUnauthorized();
    }

    public function test_valida_sis_ci_y_ambiente(): void
    {
        $this->postJson($this->lateEntryUrl('verificar'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cod_sis']);
        $this->postJson(
            $this->lateEntryUrl('verificar'),
            ['cod_sis' => '12345678', 'ci' => 'ABC', 'id_ambiente' => 'x']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cod_sis', 'ci', 'id_ambiente'])
            ->assertJsonPath('errors.cod_sis.0', 'El código SIS debe tener exactamente 9 dígitos.');
    }

    public function test_un_examen_inexistente_o_con_id_no_numerico_es_404(): void
    {
        $this->postJson('/api/control-ingreso/examenes/999999999/retrasos/verificar', ['cod_sis' => '999999999'])
            ->assertNotFound();
        $this->postJson('/api/control-ingreso/examenes/abc/retrasos/verificar', ['cod_sis' => '999999999'])
            ->assertNotFound();
    }
}
