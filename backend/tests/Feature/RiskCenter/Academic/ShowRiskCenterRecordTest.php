<?php

namespace Tests\Feature\RiskCenter\Academic;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-046: GET /api/central-riesgo/{id}, el detalle de solo lectura de un registro.
 */
class ShowRiskCenterRecordTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
    }

    public function test_sin_token_responde_401(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAsGuest();

        $this->getJson('/api/central-riesgo/' . $record['id_cr_estudiante'])->assertUnauthorized();
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $this->getJson('/api/central-riesgo/' . $record['id_cr_estudiante'])->assertForbidden();
    }

    public function test_una_cuenta_inactiva_recibe_403(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $user = $this->createAccount(Role::ADMINISTRADOR, User::ESTADO_INACTIVO);
        $this->actAs($user);

        $this->getJson('/api/central-riesgo/' . $record['id_cr_estudiante'])->assertForbidden();
    }

    /** @return array<string, array{0: string}> */
    public function allowedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'administrador' => [Role::ADMINISTRADOR]];
    }

    /**
     * @dataProvider allowedRoles
     */
    public function test_docente_y_administrador_ven_el_detalle_completo(string $role): void
    {
        $detail = $this->createFullRecord();
        $this->actAs($this->createAccount($role));

        $this->getJson('/api/central-riesgo/' . $detail['id'])
            ->assertOk()
            ->assertExactJson(['data' => [
                'id_cr_estudiante' => $detail['id'],
                'estudiante' => [
                    'id_estudiante' => $detail['student'],
                    'nombre_completo' => 'José Luis Pérez Núñez',
                    'cod_sis' => '202312345',
                ],
                'tipo_falta' => ['id_falta' => $this->context['id_tipo_falta'], 'nombre' => $detail['type']],
                'examen' => [
                    'id_examen' => $this->context['id_examen'],
                    'nombre' => '1er Parcial',
                    'materia' => 'Calculo I',
                    'carrera' => 'Ingenieria de Sistemas',
                    'fecha' => '2026-10-20',
                ],
                'ambiente' => ['nro_aula' => $detail['room'], 'hora_intento' => '2026-10-20T14:03:00+00:00'],
                'descripcion' => 'El CI presentado no coincide con el registrado.',
                'reportante' => ['nombre_completo' => 'Daniela Ferrufino Soliz', 'fecha' => '2026-10-20T14:05:00+00:00'],
                'aprobador' => ['nombre_completo' => 'Marcelo Quiroga Andrade', 'fecha' => '2026-10-20T14:21:00+00:00'],
                'observacion' => 'Confirmado con el carnet original en la puerta.',
                'fecha_registro' => '2026-10-20T14:21:00+00:00',
                'evidencia' => ['nombre_archivo' => 'foto_carnet.jpg', 'tamano_bytes' => 5, 'disponible' => true],
            ]]);
    }

    public function test_sin_intento_de_ingreso_no_hay_ambiente(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $response = $this->getJson('/api/central-riesgo/' . $record['id_cr_estudiante'])
            ->assertOk()
            ->assertJsonPath('data.ambiente', null)
            ->assertJsonPath('data.evidencia', null)
            ->assertJsonPath('data.examen.carrera', null)
            ->assertJsonPath('data.examen.materia', null);

        // La carrera es la del examen: el estudiante no la lleva.
        $this->assertArrayNotHasKey('carrera', $response->json('data.estudiante'));
    }

    public function test_si_el_archivo_de_la_evidencia_falta_lo_indica_sin_fallar(): void
    {
        $record = $this->insertApprovedRecord($this->context, [
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/falta.png',
            'evidencia_public_id' => 'evidencias/reportes/falta.png',
        ]);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo/' . $record['id_cr_estudiante'])
            ->assertOk()
            ->assertJsonPath('data.evidencia', ['nombre_archivo' => 'falta.png', 'tamano_bytes' => null, 'disponible' => false]);
    }

    public function test_el_detalle_no_expone_la_url_publica_de_la_evidencia(): void
    {
        $detail = $this->createFullRecord();
        $this->actAs($this->createAccount(Role::DOCENTE));

        $body = $this->getJson('/api/central-riesgo/' . $detail['id'])->assertOk()->getContent();

        $this->assertStringNotContainsString('http', $body);
        $this->assertStringNotContainsString('/storage/', $body);
        $this->assertStringNotContainsString('evidencia_url', $body);
    }

    /** @return array<string, array{0: string}> */
    public function unknownIds(): array
    {
        return [
            'que no existe' => ['999999'],
            'cero' => ['0'],
            'no numerico' => ['abc'],
            'con signo' => ['-1'],
            'decimal' => ['1.5'],
            'mayor al entero de la base' => ['2147483648'],
            'diez nueves' => ['9999999999'],
            'de once digitos' => ['12345678901'],
            'de veinte digitos' => ['99999999999999999999'],
        ];
    }

    /**
     * @dataProvider unknownIds
     */
    public function test_un_id_inexistente_o_fuera_de_rango_responde_404_nunca_500(string $id): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo/' . $id)->assertNotFound();
    }

    /**
     * @return array{id: int, student: int, type: string, room: string}
     */
    private function createFullRecord(): array
    {
        $pair = $this->insertCareerSubjectPair('Ingenieria de Sistemas', 'Calculo I');
        DB::table('examen')->where('id_examen', $this->context['id_examen'])->update([
            'nombre_examen' => '1er Parcial',
            'fecha' => '2026-10-20',
            'id_carrera' => $pair['id_carrera'],
            'id_materia' => $pair['id_materia'],
        ]);
        DB::table('usuario')->where('id_usuario', $this->context['id_usuario'])->update([
            'nombre' => 'Daniela',
            'apellido_paterno' => 'Ferrufino',
            'apellido_materno' => 'Soliz',
        ]);
        $approver = $this->createAccount(Role::DOCENTE);
        DB::table('usuario')->where('id_usuario', $approver->id_usuario)->update([
            'nombre' => 'Marcelo',
            'apellido_paterno' => 'Quiroga',
            'apellido_materno' => 'Andrade',
        ]);
        $student = $this->insertStudent('José Luis', 'Pérez', 'Núñez', '202312345');
        $context = array_merge($this->context, ['id_estudiante' => $student]);
        $attemptId = $this->insertEntryAttempt($context, ['registrado_en' => '2026-10-20 14:03:00+00']);
        Storage::disk('public')->put('evidencias/reportes/foto_carnet.jpg', 'datos');

        $record = $this->insertApprovedRecord($context, [
            'id_intento' => $attemptId,
            'descripcion' => 'El CI presentado no coincide con el registrado.',
            'fecha_reporte' => '2026-10-20 14:05:00+00',
            'fecha_revision' => '2026-10-20 14:21:00+00',
            'id_usuario_revisor' => $approver->id_usuario,
            'observacion_revision' => 'Confirmado con el carnet original en la puerta.',
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/foto_carnet.jpg',
            'evidencia_public_id' => 'evidencias/reportes/foto_carnet.jpg',
        ], '2026-10-20 14:21:00+00');

        return [
            'id' => $record['id_cr_estudiante'],
            'student' => $student,
            'type' => (string) DB::table('tipo_falta')->where('id_falta', $this->context['id_tipo_falta'])->value('nombre'),
            'room' => (string) DB::table('ambiente')->where('id_ambiente', $this->context['id_ambiente'])->value('nro_aula'),
        ];
    }
}
