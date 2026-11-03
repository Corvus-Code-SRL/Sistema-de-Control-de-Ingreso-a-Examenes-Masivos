<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

class RegisterExamSituationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
    }

    public function testRoomChangePreservesAttendanceAndRecordsActor(): void
    {
        $before = (array) $this->attendance();
        $reportsBefore = DB::table('reporte_estudiante')->count();
        $riskBefore = DB::table('central_riesgo')->count();

        $response = $this->postJson($this->situationUrl(), $this->situationPayload(['motivo' => '  Ruido  ']))
            ->assertCreated()
            ->assertJsonPath('mensaje', 'Situación registrada')
            ->assertJsonPath('data.ambiente_origen.id_ambiente', $this->aulaId)
            ->assertJsonPath('data.ambiente_destino.id_ambiente', $this->otraAulaId)
            ->assertJsonPath('data.registrante.id_usuario', $this->docenteId);

        $this->assertSame('Ruido', $response->json('data.motivo'));
        $this->assertNotEmpty($response->json('data.registrado_en'));
        $this->assertSame(array_replace($before, ['id_ambiente' => $this->otraAulaId]), (array) $this->attendance());
        $this->assertDatabaseHas('situacion_examen', [
            'id_situacion' => $response->json('data.id_situacion'),
            'id_usuario_registrante' => $this->docenteId,
            'tipo' => 'CAMBIO_AMBIENTE',
            'motivo' => 'Ruido',
        ]);
        $this->assertDatabaseHas('log', [
            'id_usuario' => $this->docenteId,
            'tabla_afectada' => 'situacion_examen',
            'id_accion' => DB::table('accion')->where('operacion', 'CAMBIAR_AMBIENTE')->value('id_accion'),
        ]);
        $this->assertSame($reportsBefore, DB::table('reporte_estudiante')->count());
        $this->assertSame($riskBefore, DB::table('central_riesgo')->count());
    }

    public function testIdentificationKeepsEveryAttendanceField(): void
    {
        $before = (array) $this->attendance();
        $reportsBefore = DB::table('reporte_estudiante')->count();
        $riskBefore = DB::table('central_riesgo')->count();

        $this->postJson($this->situationUrl(), [
            'tipo' => 'IDENTIFICACION',
            'motivo' => '  Documento deteriorado.  ',
        ])->assertCreated()->assertJsonPath('data.ambiente_destino', null)
            ->assertJsonPath('data.motivo', 'Documento deteriorado.');

        $this->assertSame($before, (array) $this->attendance());
        $this->assertSame($reportsBefore, DB::table('reporte_estudiante')->count());
        $this->assertSame($riskBefore, DB::table('central_riesgo')->count());
        $this->assertDatabaseHas('log', [
            'id_usuario' => $this->docenteId,
            'tabla_afectada' => 'situacion_examen',
            'id_accion' => DB::table('accion')->where('operacion', 'OBSERVAR_IDENTIDAD')->value('id_accion'),
        ]);
    }

    public function testEmptyChangeReasonReturnsTheAcceptanceMessage(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload(['motivo' => '   ']))
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'Indique el motivo del cambio.');
        $this->assertSame(0, DB::table('situacion_examen')->count());
        $this->assertSame($this->aulaId, (int) $this->attendance()->id_ambiente);
    }

    public function testShortIdentificationReturnsTheAcceptanceMessage(): void
    {
        $this->postJson($this->situationUrl(), ['tipo' => 'IDENTIFICACION', 'motivo' => 'Duda'])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'Describa lo ocurrido (mínimo 10 caracteres).');
        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    public function testExactlyTenIdentificationCharactersAreAccepted(): void
    {
        $this->postJson($this->situationUrl(), ['tipo' => 'IDENTIFICACION', 'motivo' => '1234567890'])
            ->assertCreated();
    }

    public function testRejectsTextOverFiveHundredCharacters(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload(['motivo' => str_repeat('a', 501)]))
            ->assertStatus(422)->assertJsonValidationErrors('motivo');
    }

    /** @dataProvider invalidDestinationCases */
    public function testRejectsInvalidDestination(string $case): void
    {
        $destination = $case === 'current' ? $this->aulaId : $this->aulaInactivaId;
        $payload = $this->situationPayload(['id_ambiente_destino' => $destination]);

        if ($case === 'missing') {
            unset($payload['id_ambiente_destino']);
        }

        $this->postJson($this->situationUrl(), $payload)
            ->assertStatus(422)->assertJsonValidationErrors('id_ambiente_destino');
        $this->assertSame($this->aulaId, (int) $this->attendance()->id_ambiente);
        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    public static function invalidDestinationCases(): array
    {
        return [['current'], ['outside'], ['missing']];
    }

    public function testSingleRoomExamCannotBeMovedToAnUnassignedRoom(): void
    {
        $this->situationExam->classrooms()->detach($this->otraAulaId);
        $this->postJson($this->situationUrl(), $this->situationPayload())
            ->assertStatus(422)->assertJsonValidationErrors('id_ambiente_destino');
    }

    public function testIdentificationRejectsANonemptyDestination(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload([
            'tipo' => 'IDENTIFICACION', 'motivo' => 'Documento deteriorado.',
        ]))->assertStatus(422)->assertJsonValidationErrors('id_ambiente_destino');
    }

    public function testExpulsionIsNotASituationType(): void
    {
        $this->postJson($this->situationUrl(), ['tipo' => 'EXPULSION', 'motivo' => 'Motivo de prueba.'])
            ->assertStatus(422)->assertJsonValidationErrors('tipo');
    }

    /** @dataProvider prohibitedFields */
    public function testClientCannotSetProtectedFields(string $field, $value): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload([$field => $value]))
            ->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    public static function prohibitedFields(): array
    {
        return [
            ['id_usuario_registrante', '22222222-2222-4222-8222-222222222222'],
            ['id_estudiante', 999],
            ['id_examen', 999],
            ['id_ambiente_origen', 999],
            ['registrado_en', '2000-01-01'],
            ['estado_ingreso', 'EXPULSADO'],
            ['id_tipo_falta', 1],
            ['evidencia_url', 'evidencias/ajena.png'],
            ['retirar_estudiante', true],
        ];
    }

     /** @dataProvider closedStates */
    public function testOnlyInProgressExamsAcceptSituations(string $state): void
    {
        if ($state === Exam::CANCELADO) {
            // La base solo permite cancelar un examen que todavía está programado.
            $this->situationExam = $this->createExam(
                ['estado' => Exam::PROGRAMADO],
                [$this->aulaId, $this->otraAulaId]
            );
        }

        $this->situationExam->update(['estado' => $state]);
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertStatus(409);
        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    public static function closedStates(): array
    {
        return [[Exam::PROGRAMADO], [Exam::EN_INGRESO], [Exam::FINALIZADO], [Exam::CANCELADO]];
    }

    public function testAbsentStudentCannotReceiveASituation(): void
    {
        DB::table('examen_estudiante')
            ->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)
            ->update([
                'estado_ingreso' => 'NO_INGRESO',
                'hora_ingreso' => null,
                'id_usuario_controlador' => null,
                'registrado_en' => null,
            ]);

        $this->postJson($this->situationUrl(), $this->situationPayload())->assertStatus(409);
        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    public function testStudentWithoutExamAttendanceIsRejected(): void
    {
        DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)->delete();
        $this->postJson($this->situationUrl(), $this->situationPayload())
            ->assertStatus(422)->assertJsonValidationErrors('estudiante');
    }

    public function testLateStudentKeepsLateStatusAfterChangingRoom(): void
    {
        DB::table('examen_estudiante')->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)
            ->update(['estado_ingreso' => 'CON_RETRASO']);
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        $this->assertSame('CON_RETRASO', $this->attendance()->estado_ingreso);
    }

    public function testOtherTeacherCannotRegisterASituation(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertForbidden();
    }

    public function testAssistantCanMoveStudentFromOwnRoomButCannotFollowThemIntoAnother(): void
    {
        $this->actAsSituationAssistant($this->aulaId);
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated()
            ->assertJsonPath('data.registrante.id_usuario', $this->otroDocenteId);
        $this->postJson($this->situationUrl(), [
            'tipo' => 'IDENTIFICACION', 'motivo' => 'Documento deteriorado.',
        ])->assertForbidden();
        $this->assertSame(1, DB::table('situacion_examen')->count());
    }

    public function testUnassignedAssistantCannotControlAMultiRoomExam(): void
    {
        $this->actAsSituationAssistant(null);
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertForbidden();
    }

    public function testUnassignedAssistantCanObserveIdentityInASingleRoomExam(): void
    {
        $this->situationExam->classrooms()->detach($this->otraAulaId);
        $this->actAsSituationAssistant(null);
        $this->postJson($this->situationUrl(), [
            'tipo' => 'IDENTIFICACION', 'motivo' => 'Documento deteriorado.',
        ])->assertCreated();
    }

    public function testGuestCannotRegisterASituation(): void
    {
        $this->actAsGuest();
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertUnauthorized();
    }

    public function testSeveralFactsForTheSameStudentAreAllowed(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();
        $this->postJson($this->situationUrl(), [
            'tipo' => 'IDENTIFICACION', 'motivo' => 'Documento deteriorado.',
        ])->assertCreated()->assertJsonPath('data.ambiente_origen.id_ambiente', $this->otraAulaId);
        $this->assertSame(2, DB::table('situacion_examen')->count());
    }

    public function testAuditFailureRollsBackRoomAndSituation(): void
    {
        $before = (array) $this->attendance();
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('registrar')->once()->andThrow(new RuntimeException('Fallo de auditoría de prueba'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson($this->situationUrl(), $this->situationPayload());
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría de prueba', $exception->getMessage());
        }

        $this->assertSame($before, (array) $this->attendance());
        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    private function attendance(): object
    {
        return DB::table('examen_estudiante')
            ->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)
            ->first();
    }
}