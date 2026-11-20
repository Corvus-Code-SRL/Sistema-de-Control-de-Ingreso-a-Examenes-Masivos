<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Services\Exams\Distribution\DistributionVersion;
use App\Support\Exams\DistributionMessages;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionSaveTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_guarda_completo_y_devuelve_resumen_actualizado(): void
    {
        $exam = $this->distributionExam();
        $response = $this->saveDistribution($exam)->assertOk()
            ->assertJsonPath('mensaje', DistributionMessages::SAVED)
            ->assertJsonPath('data.ambientes.0.asignados', 2)
            ->assertJsonPath('data.ambientes.1.asignados', 2);

        $this->assertNotSame(DistributionVersion::fromRows([]), $response->json('data.version'));
        $this->assertSame($response->json('data'), $this->getJson($this->distributionUrl($exam))->json('data'));
        $this->assertSame(4, DB::table('distribucion_ambiente')->where('id_examen', $exam->id_examen)->count());
        $this->assertSame(0, DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count());
    }

    public function test_acepta_ajuste_manual_si_hay_lugar_y_actualiza_ocupacion(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $assignments = $this->distributionProposal($exam);
        $assignments[0]['id_ambiente'] = $this->otraAulaId;

        $this->putJson($this->distributionUrl($exam), [
            'version' => $saved['version'], 'asignaciones' => $assignments,
        ])->assertOk()->assertJsonPath('data.ambientes.0.asignados', 1)
            ->assertJsonPath('data.ambientes.1.asignados', 3);
    }

    public function test_ambiente_lleno_rechaza_el_cambio_y_conserva_la_distribucion(): void
    {
        $exam = $this->distributionExam(4, [2, 2]);
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $assignments = $this->distributionProposal($exam);
        $assignments[0]['id_ambiente'] = $this->otraAulaId;

        $this->putJson($this->distributionUrl($exam), [
            'version' => $saved['version'], 'asignaciones' => $assignments,
        ])->assertUnprocessable()->assertJsonPath('errors.asignaciones.0', DistributionMessages::ROOM_FULL);
        $this->assertSame($saved, $this->getJson($this->distributionUrl($exam))->json('data'));
    }

    public function test_capacidad_total_insuficiente_informa_cuantos_lugares_faltan(): void
    {
        $exam = $this->distributionExam(7, [2, 3]);
        $assignments = array_map(fn (int $id): array => [
            'id_estudiante' => $id, 'id_ambiente' => $this->aulaId,
        ], $this->distributionStudentIds($exam));

        $this->putJson($this->distributionUrl($exam), [
            'version' => $this->distributionVersion($exam), 'asignaciones' => $assignments,
        ])->assertUnprocessable()->assertJsonPath(
            'errors.asignaciones.0', sprintf(DistributionMessages::MISSING_SEATS, 2)
        );
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
    }

    public function test_rechaza_nuevo_no_habilitado_o_estudiante_ajeno_y_omisiones(): void
    {
        $exam = $this->distributionExam();
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);
        $this->setDistributionEligibility($exam, $assignments[0]['id_estudiante'], 'NO_HABILITADO');

        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertUnprocessable()->assertJsonValidationErrors('asignaciones');
        $assignments = array_slice($assignments, 1);
        $incomplete = array_slice($assignments, 1);
        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $incomplete,
        ])->assertUnprocessable();
        $assignments[0]['id_estudiante'] = 2147483647;
        $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ])->assertUnprocessable();
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
    }

    public function test_rechaza_un_ambiente_que_no_pertenece_al_examen(): void
    {
        $exam = $this->distributionExam();
        $assignments = $this->distributionProposal($exam);
        $assignments[0]['id_ambiente'] = $this->aulaInactivaId;

        $this->putJson($this->distributionUrl($exam), [
            'version' => $this->distributionVersion($exam), 'asignaciones' => $assignments,
        ])->assertUnprocessable()->assertJsonPath('errors.asignaciones.0', DistributionMessages::FOREIGN_ROOM);
    }

    /** @dataProvider invalidPayloads */
    public function test_valida_la_estructura_antes_de_guardar(array $payload, string $field): void
    {
        $exam = $this->distributionExam();

        $this->putJson($this->distributionUrl($exam), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors($field);
        $this->assertSame(0, DB::table('distribucion_ambiente')->count());
    }

    /** @return array<string, array{0:array{version?:string, asignaciones?:array|string}, 1:string}> */
    public function invalidPayloads(): array
    {
        $version = DistributionVersion::fromRows([]);

        return [
            'sin version' => [['asignaciones' => []], 'version'],
            'version malformada' => [['version' => 'vieja', 'asignaciones' => []], 'version'],
            'sin lista' => [['version' => $version], 'asignaciones'],
            'lista no array' => [['version' => $version, 'asignaciones' => 'x'], 'asignaciones'],
            'estudiante repetido' => [['version' => $version, 'asignaciones' => [
                ['id_estudiante' => 1, 'id_ambiente' => 1], ['id_estudiante' => 1, 'id_ambiente' => 2],
            ]], 'asignaciones.1.id_estudiante'],
            'ambiente ausente' => [['version' => $version, 'asignaciones' => [
                ['id_estudiante' => 1],
            ]], 'asignaciones.0.id_ambiente'],
            'ambiente nulo' => [['version' => $version, 'asignaciones' => [
                ['id_estudiante' => 1, 'id_ambiente' => null],
            ]], 'asignaciones.0.id_ambiente'],
            'elemento escalar' => [['version' => $version, 'asignaciones' => [42]], 'asignaciones.0.id_estudiante'],
            'id fuera de rango' => [['version' => $version, 'asignaciones' => [
                ['id_estudiante' => 2147483648, 'id_ambiente' => 1],
            ]], 'asignaciones.0.id_estudiante'],
        ];
    }

    public function test_los_identificadores_numericos_como_texto_se_normalizan(): void
    {
        $exam = $this->distributionExam();
        $assignments = array_map(fn (array $a): array => array_map('strval', $a), $this->distributionProposal($exam));

        $this->putJson($this->distributionUrl($exam), [
            'version' => $this->distributionVersion($exam), 'asignaciones' => $assignments,
        ])->assertOk();
    }

    public function test_consultar_o_pedir_otra_propuesta_no_cambia_lo_confirmado(): void
    {
        $exam = $this->distributionExam();
        $saved = $this->saveDistribution($exam)->assertOk()->json('data');
        $logs = DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->count();

        $this->distributionProposal($exam);
        $this->assertSame($saved, $this->getJson($this->distributionUrl($exam))->json('data'));
        $this->assertSame($logs, DB::table('log')->where('tabla_afectada', 'distribucion_ambiente')->count());
    }

    public function test_un_ambiente_no_permite_propuesta_ni_guardado(): void
    {
        $exam = $this->distributionExam(2, [30]);

        $this->postJson($this->distributionUrl($exam) . '/propuesta')->assertUnprocessable();
        $this->putJson($this->distributionUrl($exam), [
            'version' => $this->distributionVersion($exam), 'asignaciones' => [],
        ])->assertUnprocessable();
    }
}
