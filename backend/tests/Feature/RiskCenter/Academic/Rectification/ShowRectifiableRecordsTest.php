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
 * HU-062: los registros de un estudiante con su estado («Original» o «Rectificado»), el detalle con el
 * historial de versiones y el catálogo de tipos de falta.
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
        return '/api/rectificacion-riesgo/estudiantes/' . ($studentId ?? $this->context['id_estudiante']) . '/registros';
    }

    private function detailUrl(int $recordId): string
    {
        return '/api/rectificacion-riesgo/registros/' . $recordId;
    }

    public function test_sin_token_responde_401_en_todas_las_rutas_de_lectura(): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAsGuest();

        $this->getJson($this->recordsUrl())->assertUnauthorized();
        $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertUnauthorized();
        $this->getJson('/api/rectificacion-riesgo/tipos-falta')->assertUnauthorized();
    }

    /** @return array<string, array{0: string}> */
    public function deniedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'auxiliar' => [Role::AUXILIAR], 'invitado' => [Role::INVITADO]];
    }

    /** @dataProvider deniedRoles */
    public function test_quien_no_es_administrador_recibe_403_en_las_tres_lecturas(string $role): void
    {
        $record = $this->insertApprovedRecord($this->context);
        $this->actAs($this->createAccount($role));

        $this->getJson($this->recordsUrl())->assertForbidden();
        $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertForbidden();
        $this->getJson('/api/rectificacion-riesgo/tipos-falta')->assertForbidden();
    }

    public function test_lista_los_registros_del_mas_reciente_al_mas_antiguo_con_su_estado(): void
    {
        $older = $this->insertApprovedRecord($this->context, [], '2026-09-01 10:00:00+00');
        $newer = $this->insertApprovedRecord($this->context, [], '2026-10-05 10:00:00+00');
        $this->rectify($older['id_cr_estudiante'])->assertCreated();
        $this->rectify($older['id_cr_estudiante'], ['version_base' => 2])->assertCreated();

        $response = $this->getJson($this->recordsUrl())
            ->assertOk()
            ->assertJsonPath('data.estudiante.id_estudiante', $this->context['id_estudiante'])
            ->assertJsonPath('data.estudiante.cod_sis', DB::table('estudiante')
                ->where('id_estudiante', $this->context['id_estudiante'])->value('cod_sis'))
            ->assertJsonCount(2, 'data.registros');

        $this->assertSame(
            [$newer['id_cr_estudiante'], $older['id_cr_estudiante']],
            $response->json('data.registros.*.id_cr_estudiante')
        );

        $original = $response->json('data.registros.0');
        $this->assertSame('Original', $original['estado']);
        $this->assertSame(0, $original['numero_rectificaciones']);
        $this->assertNull($original['ultima_rectificacion']);
        $this->assertSame(1, $original['version_vigente']);

        $rectified = $response->json('data.registros.1');
        $this->assertSame('Rectificado', $rectified['estado']);
        $this->assertSame(2, $rectified['numero_rectificaciones']);
        $this->assertSame(3, $rectified['version_vigente']);
        $this->assertSame(
            DB::table('central_riesgo_version')
                ->where('id_cr_estudiante', $older['id_cr_estudiante'])
                ->where('es_vigente', true)
                ->value('id_tipo_falta'),
            $rectified['tipo_falta']['id_falta']
        );
        $this->assertNotNull($rectified['ultima_rectificacion']);
        $this->assertSame('2026-09-01T10:00:00+00:00', $rectified['fecha_registro']);
    }

    public function test_cada_registro_trae_fecha_examen_tipo_de_falta_y_aprobador(): void
    {
        $record = $this->insertApprovedRecord($this->context);

        $item = $this->getJson($this->recordsUrl())->assertOk()->json('data.registros.0');

        $this->assertSame($record['id_cr_estudiante'], $item['id_cr_estudiante']);
        $this->assertSame($this->context['id_examen'], $item['examen']['id_examen']);
        $this->assertSame('Examen de prueba', $item['examen']['nombre']);
        $this->assertSame('2026-10-01', $item['examen']['fecha']);
        $this->assertSame($this->context['id_tipo_falta'], $item['tipo_falta']['id_falta']);
        $this->assertSame('Docente Reportante', $item['aprobador']['nombre_completo']);
    }

    public function test_un_estudiante_sin_registros_devuelve_la_lista_vacia(): void
    {
        $this->getJson($this->recordsUrl())
            ->assertOk()
            ->assertJsonPath('data.estudiante.id_estudiante', $this->context['id_estudiante'])
            ->assertJsonCount(0, 'data.registros');
    }

    public function test_solo_lista_los_registros_de_ese_estudiante(): void
    {
        $this->insertApprovedRecord($this->context);
        $otherStudent = $this->insertStudent('Otro', 'Estudiante', null, '202499991');
        $otherContext = array_merge($this->context, ['id_estudiante' => $otherStudent]);
        $this->insertApprovedRecord($otherContext);

        $this->getJson($this->recordsUrl($otherStudent))->assertOk()->assertJsonCount(1, 'data.registros');
    }

    public function test_un_estudiante_inexistente_responde_404(): void
    {
        $this->getJson($this->recordsUrl(999999999))->assertNotFound();
        $this->getJson($this->recordsUrl(1234567890))->assertNotFound();
    }

    public function test_el_detalle_de_un_registro_original_muestra_la_version_uno_vigente(): void
    {
        $record = $this->insertApprovedRecord($this->context, ['descripcion' => 'Descripción original.']);

        $this->getJson($this->detailUrl($record['id_cr_estudiante']))
            ->assertOk()
            ->assertJsonPath('data.estado', 'Original')
            ->assertJsonPath('data.descripcion', 'Descripción original.')
            ->assertJsonPath('data.estudiante.id_estudiante', $this->context['id_estudiante'])
            ->assertJsonPath('data.examen.nombre', 'Examen de prueba')
            ->assertJsonPath('data.reportante.nombre_completo', 'Docente Reportante')
            ->assertJsonPath('data.aprobador.nombre_completo', 'Docente Reportante')
            ->assertJsonCount(1, 'data.historial')
            ->assertJsonPath('data.historial.0.numero_version', 1)
            ->assertJsonPath('data.historial.0.estado', 'Vigente')
            ->assertJsonPath('data.historial.0.motivo', null)
            ->assertJsonPath('data.historial.0.fecha', '2026-10-02T12:00:00+00:00');

        $this->assertSame(0, DB::table('central_riesgo_version')->count());
    }

    public function test_el_historial_va_de_la_version_mas_nueva_a_la_mas_antigua(): void
    {
        $record = $this->insertApprovedRecord($this->context, ['descripcion' => 'Original.']);
        $id = $record['id_cr_estudiante'];
        $this->rectify($id, ['motivo' => 'Primer motivo válido', 'descripcion' => 'Segunda.'])->assertCreated();
        $this->rectify($id, ['motivo' => 'Segundo motivo válido', 'descripcion' => 'Tercera.', 'version_base' => 2])
            ->assertCreated();

        $response = $this->getJson($this->detailUrl($id))->assertOk();

        $this->assertSame([3, 2, 1], $response->json('data.historial.*.numero_version'));
        $this->assertSame(['Vigente', 'Anterior', 'Anterior'], $response->json('data.historial.*.estado'));
        $this->assertSame(['Segundo motivo válido', 'Primer motivo válido', null], $response->json('data.historial.*.motivo'));
        $this->assertSame(['Tercera.', 'Segunda.', 'Original.'], $response->json('data.historial.*.descripcion'));
        $this->assertSame('Tercera.', $response->json('data.descripcion'));
        $this->assertSame('Rectificado', $response->json('data.estado'));
        $this->assertSame(2, $response->json('data.numero_rectificaciones'));
        $this->assertSame($response->json('data.historial.0.fecha'), $response->json('data.ultima_rectificacion'));
    }

    public function test_cada_version_muestra_su_autor(): void
    {
        $record = $this->insertApprovedRecord($this->context);

        $this->rectify($record['id_cr_estudiante'])->assertCreated();

        $history = $this->getJson($this->detailUrl($record['id_cr_estudiante']))->assertOk()->json('data.historial');

        $this->assertSame('Docente Reportante', $history[1]['autor']['nombre_completo']);
        $this->assertStringStartsWith(
            DB::table('usuario')->where('id_usuario', $this->administratorId)->value('nombre'),
            $history[0]['autor']['nombre_completo']
        );
    }

    public function test_un_registro_inexistente_responde_404(): void
    {
        $this->getJson($this->detailUrl(999999999))->assertNotFound();
        $this->getJson($this->detailUrl(1234567890))->assertNotFound();
    }

    public function test_el_catalogo_de_tipos_de_falta_sale_ordenado_por_nombre(): void
    {
        $this->insertFaultType('Zzz falta final');
        $this->insertFaultType('Aaa falta inicial');

        $names = $this->getJson('/api/rectificacion-riesgo/tipos-falta')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id_falta', 'nombre', 'descripcion']]])
            ->json('data.*.nombre');

        $this->assertContains('Aaa falta inicial', $names);
        $this->assertContains('Zzz falta final', $names);
        $this->assertSame(count($names), count(array_unique($names)));
        $this->assertLessThan(array_search('Zzz falta final', $names), array_search('Aaa falta inicial', $names));
    }
}
