<?php

namespace Tests\Feature\EntryControl;

use App\Services\EntryControl\VerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsLateEntryExam;
use Tests\TestCase;

/**
 * Cruce HU-042 con HU-037: un estudiante retirado del examen no puede registrarse con retraso
 * y el mensaje indica a qué hora fue expulsado.
 */
class LateEntryExpelledStudentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLateEntryExam;

    private const MESSAGE = 'El estudiante fue expulsado de este examen a las 10:40';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLateEntryExam();
        $this->actAsTeacher($this->docenteId);
        $this->insertEntry($this->secondStudentId, '10:01:00', $this->otraAulaId);
        DB::table('examen_estudiante')->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $this->secondStudentId)
            ->update(['expulsado_en' => Carbon::parse('2026-10-30T14:40:00Z')]);
    }

    public function test_la_verificacion_indica_que_fue_expulsado_y_no_autoriza(): void
    {
        $this->postJson($this->lateEntryUrl('verificar'), [
            'cod_sis' => $this->studentColumn($this->secondStudentId, 'cod_sis'),
            'ci' => $this->studentColumn($this->secondStudentId, 'ci'),
            'id_ambiente' => $this->otraAulaId,
        ])->assertOk()
            ->assertJsonPath('data.autorizado', false)
            ->assertJsonPath('data.veredicto', VerificationService::DUPLICATE)
            ->assertJsonPath('data.motivo', self::MESSAGE);
    }

    public function test_el_registro_directo_se_bloquea_con_el_mensaje_de_expulsion(): void
    {
        $this->postJson($this->lateEntryUrl('registrar'), [
            'id_estudiante' => $this->secondStudentId,
            'ci' => $this->studentColumn($this->secondStudentId, 'ci'),
            'id_ambiente' => $this->otraAulaId,
        ])->assertStatus(409)
            ->assertJsonPath('veredicto', VerificationService::DUPLICATE)
            ->assertJsonPath('message', self::MESSAGE);

        $this->assertSame(0, DB::table('solicitud_ingreso_retraso')->count());
    }

    public function test_la_solicitud_de_un_auxiliar_tambien_se_bloquea(): void
    {
        $this->actAsUserId($this->assistantBId);

        $this->postJson($this->lateEntryUrl('solicitudes'), [
            'id_estudiante' => $this->secondStudentId,
            'id_ambiente' => $this->otraAulaId,
        ])->assertStatus(409)->assertJsonPath('message', self::MESSAGE);

        $this->assertSame(0, DB::table('solicitud_ingreso_retraso')->count());
    }
}
