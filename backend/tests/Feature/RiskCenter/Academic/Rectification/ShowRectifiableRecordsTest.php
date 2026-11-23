<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: los registros de un estudiante (activos y, aparte, ya rectificados) y la ficha completa de un
 * registro activo para la tarjeta de solo lectura.
 */
class ShowRectifiableRecordsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRectifications;
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

    private function recordsUrl(?int $studentId = null): string
    {
        $id = $studentId ?? $this->context['id_estudiante'];

        return "/api/rectificacion-riesgo/estudiantes/{$id}/registros";
    }

    private function detailUrl(int $recordId): string
    {
        return '/api/rectificacion-riesgo/registros/' . $recordId;
    }

    public function test_sin_token_responde_401_en_las_dos_lecturas(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAsGuest();

        $this->getJson($this->recordsUrl())->assertUnauthorized();
        $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertUnauthorized();
    }

    /** @return array<string, array{0: string}> */
    public function deniedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'auxiliar' => [Role::AUXILIAR], 'invitado' => [Role::INVITADO]];
    }

    /** @dataProvider deniedRoles */
    public function test_quien_no_es_administrador_recibe_403_en_las_dos_lecturas(string $role): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAs($this->createAccount($role));

        $this->getJson($this->recordsUrl())->assertForbidden();
        $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertForbidden();
    }

    public function test_el_catalogo_de_tipos_de_falta_de_la_version_anterior_ya_no_existe(): void
    {
        $this->getJson('/api/rectificacion-riesgo/tipos-falta')->assertNotFound();
    }

    public function test_lista_los_activos_del_mas_reciente_al_mas_antiguo(): void
    {
        $older = $this->insertApprovedRecord($this->context, [], '2026-09-01 10:00:00+00');
        $newer = $this->insertApprovedRecord($this->context, [], '2026-10-05 10:00:00+00');

        $response = $this->getJson($this->recordsUrl())
            ->assertOk()
            ->assertJsonPath('data.estudiante.id_estudiante', $this->context['id_estudiante'])
            ->assertJsonPath('data.estudiante.cod_sis', DB::table('estudiante')
                ->where('id_estudiante', $this->context['id_estudiante'])->value('cod_sis'))
            ->assertJsonCount(2, 'data.registros')
            ->assertJsonCount(0, 'data.rectificados');

        $this->assertSame(
            [$newer['id_cr_estudiante'], $older['id_cr_estudiante']],
            $response->json('data.registros.*.id_cr_estudiante')
        );
    }

    public function test_cada_registro_activo_trae_fecha_examen_tipo_de_falta_y_aprobador(): void
    {
        $record = $this->insertApprovedRecord($this->context);

        $item = $this->getJson($this->recordsUrl())->assertOk()->json('data.registros.0');

        $this->assertSame($record['id_cr_estudiante'], $item['id_cr_estudiante']);
        $this->assertSame($this->context['id_examen'], $item['examen']['id_examen']);
        $this->assertSame('Examen de prueba', $item['examen']['nombre']);
        $this->assertSame('2026-10-01', $item['examen']['fecha']);
        $this->assertSame($this->context['id_tipo_falta'], $item['tipo_falta']['id_falta']);
        $this->assertSame('Docente Reportante', $item['aprobador']['nombre_completo']);
        $this->assertSame('2026-10-02T12:00:00+00:00', $item['fecha_registro']);
        $this->assertNull($item['rectificacion']['solicitud_pendiente']);
        $this->assertSame(0, $item['rectificacion']['rechazos']);
        $this->assertSame(0, $item['rectificacion']['vencidas']);
        $this->assertNull($item['rectificacion']['ultimo_rechazo']);
    }

    public function test_los_rectificados_van_aparte_con_la_fecha_el_administrador_y_el_motivo(): void
    {
        $active = $this->insertApprovedRecord($this->context, [], '2026-10-05 10:00:00+00');
        $voided = $this->insertApprovedRecord($this->context, [], '2026-09-01 10:00:00+00');
        $this->voidRecord($voided['id_cr_estudiante'], null, 'Se retira: el estudiante no era el implicado.');

        $response = $this->getJson($this->recordsUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data.registros')
            ->assertJsonCount(1, 'data.rectificados');

        $this->assertSame($active['id_cr_estudiante'], $response->json('data.registros.0.id_cr_estudiante'));

        $item = $response->json('data.rectificados.0');
        $this->assertSame($voided['id_cr_estudiante'], $item['id_cr_estudiante']);
        $this->assertSame('Se retira: el estudiante no era el implicado.', $item['rectificacion']['motivo']);
        $this->assertSame('2026-10-20T15:00:00+00:00', $item['rectificacion']['fecha']);
        $this->assertStringStartsWith(
            DB::table('usuario')->where('id_usuario', $this->administratorId)->value('nombre'),
            $item['rectificacion']['administrador']['nombre_completo']
        );
        $this->assertSame('Examen de prueba', $item['examen']['nombre']);
        $this->assertSame($this->context['id_tipo_falta'], $item['tipo_falta']['id_falta']);
    }

    public function test_los_rectificados_salen_del_mas_recientemente_rectificado_al_mas_antiguo(): void
    {
        $first = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $second = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->voidRecord($first, null, 'Motivo del primero.', '2026-10-10 10:00:00+00');
        $this->voidRecord($second, null, 'Motivo del segundo.', '2026-10-12 10:00:00+00');

        $ids = $this->getJson($this->recordsUrl())->assertOk()->json('data.rectificados.*.id_cr_estudiante');

        $this->assertSame([$second, $first], $ids);
    }

    public function test_rectificar_por_la_api_mueve_el_registro_de_la_lista_activa_a_rectificados(): void
    {
        $record = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];

        $this->getJson($this->recordsUrl())
            ->assertJsonCount(1, 'data.registros')
            ->assertJsonCount(0, 'data.rectificados');
        $this->rectify($record)->assertOk();

        $this->getJson($this->recordsUrl())
            ->assertJsonCount(0, 'data.registros')
            ->assertJsonCount(1, 'data.rectificados');
    }

    public function test_un_estudiante_sin_registros_devuelve_ambas_listas_vacias(): void
    {
        $this->getJson($this->recordsUrl())
            ->assertOk()
            ->assertJsonPath('data.estudiante.id_estudiante', $this->context['id_estudiante'])
            ->assertJsonCount(0, 'data.registros')
            ->assertJsonCount(0, 'data.rectificados');
    }

    public function test_solo_lista_los_registros_de_ese_estudiante(): void
    {
        $this->insertApprovedRecord($this->context);
        $otherStudent = $this->insertStudent('Otro', 'Estudiante', null, '202499991');
        $this->insertApprovedRecord(array_merge($this->context, ['id_estudiante' => $otherStudent]));

        $this->getJson($this->recordsUrl($otherStudent))->assertOk()->assertJsonCount(1, 'data.registros');
    }

    public function test_un_estudiante_inexistente_responde_404(): void
    {
        $this->getJson($this->recordsUrl(999999999))->assertNotFound();
        $this->getJson($this->recordsUrl(1234567890))->assertNotFound();
    }

    public function test_la_ficha_trae_toda_la_informacion_del_registro(): void
    {
        $attemptId = $this->insertEntryAttempt($this->context, ['registrado_en' => '2026-10-01 08:30:00+00']);
        $record = $this->insertApprovedRecord($this->context, [
            'descripcion' => 'Descripción completa de la falta.',
            'id_intento' => $attemptId,
            'fecha_reporte' => '2026-10-01 09:00:00+00',
        ], '2026-10-02 12:00:00+00');
        $student = DB::table('estudiante')->where('id_estudiante', $this->context['id_estudiante'])->first();
        DB::table('estudiante')->where('id_estudiante', $student->id_estudiante)->update(['ci' => '7654321']);

        $data = $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertOk()->json('data');

        $this->assertSame($record['id_cr_estudiante'], $data['id_cr_estudiante']);
        $this->assertSame($student->cod_sis, $data['estudiante']['cod_sis']);
        $this->assertSame('7654321', $data['estudiante']['ci']);
        $this->assertSame('Nombre Apellido', $data['estudiante']['nombre_completo']);
        $this->assertSame('Examen de prueba', $data['examen']['nombre']);
        $this->assertSame('2026-10-01', $data['examen']['fecha']);
        $this->assertArrayHasKey('materia', $data['examen']);
        $this->assertSame('2026-10-01T08:30:00+00:00', $data['hecho']['fecha_hora']);
        $this->assertSame(
            DB::table('ambiente')->where('id_ambiente', $this->context['id_ambiente'])->value('nro_aula'),
            $data['ambiente']['nro_aula']
        );
        $this->assertSame($this->context['id_tipo_falta'], $data['tipo_falta']['id_falta']);
        $this->assertSame('Descripción completa de la falta.', $data['descripcion']);
        $this->assertSame('Docente Reportante', $data['reportante']['nombre_completo']);
        $this->assertSame('2026-10-01T09:00:00+00:00', $data['reportante']['fecha']);
        $this->assertSame('Docente Reportante', $data['aprobador']['nombre_completo']);
        $this->assertSame('2026-10-02T12:00:00+00:00', $data['fecha_registro']);
    }

    public function test_la_ficha_sin_intento_usa_la_fecha_del_reporte_y_no_trae_ambiente(): void
    {
        $record = $this->insertApprovedRecord($this->context, ['fecha_reporte' => '2026-10-01 09:15:00+00']);

        $data = $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertOk()->json('data');

        $this->assertSame('2026-10-01T09:15:00+00:00', $data['hecho']['fecha_hora']);
        $this->assertNull($data['ambiente']);
    }

    public function test_la_ficha_describe_la_evidencia_sin_exponer_la_url_publica(): void
    {
        Storage::disk('public')->put('evidencias/reportes/foto_carnet.jpg', 'datos');
        $record = $this->insertApprovedRecord($this->context, [
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/foto_carnet.jpg',
            'evidencia_public_id' => 'evidencias/reportes/foto_carnet.jpg',
        ]);

        $response = $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertOk();

        $response->assertJsonPath(
            'data.evidencia',
            ['nombre_archivo' => 'foto_carnet.jpg', 'tamano_bytes' => 5, 'disponible' => true]
        );
        $this->assertStringNotContainsString('evidencia_url', $response->getContent());
        $this->assertStringNotContainsString('http://localhost/storage', $response->getContent());
    }

    public function test_la_ficha_sin_evidencia_la_devuelve_nula(): void
    {
        $record = $this->insertApprovedRecord($this->context);

        $this->getJson($this->detailUrl($record['id_cr_estudiante']))
            ->assertOk()
            ->assertJsonPath('data.evidencia', null);
    }

    public function test_la_ficha_de_un_registro_rectificado_responde_404(): void
    {
        $record = $this->insertApprovedRecord($this->context)['id_cr_estudiante'];
        $this->voidRecord($record);

        $this->getJson($this->detailUrl($record))->assertNotFound();
    }

    public function test_un_registro_inexistente_responde_404(): void
    {
        $this->getJson($this->detailUrl(999999999))->assertNotFound();
        $this->getJson($this->detailUrl(1234567890))->assertNotFound();
    }
}
