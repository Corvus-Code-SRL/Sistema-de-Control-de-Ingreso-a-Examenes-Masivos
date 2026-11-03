<?php

namespace Tests\Unit\Services\EntryControl;

use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\IdentificationService;
use App\Services\EntryControl\VerificationService;
use App\Services\RiskCenter\Academic\RiskCenterQueryService;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de VerificationService::evaluate(): sin base de datos, sin Redis y sin sesión.
 * Las dependencias son dobles que fallan si se las llama.
 */
class VerificationEvaluateTest extends TestCase
{
    private const ROOM_A = 10;
    private const ROOM_B = 20;

    private VerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $snapshots = $this->createMock(EntryControlSnapshotService::class);
        $snapshots->expects($this->never())->method($this->anything());
        $identification = $this->createMock(IdentificationService::class);
        $identification->expects($this->never())->method($this->anything());
        $riskCenter = $this->createMock(RiskCenterQueryService::class);
        $riskCenter->expects($this->never())->method($this->anything());

        $this->service = new VerificationService($snapshots, $identification, $riskCenter);
    }

    /** @param array<string, mixed> $overrides */
    private function record(array $overrides = []): array
    {
        return array_replace([
            'estudiante' => [
                'id_estudiante' => 7,
                'cod_sis' => '202400001',
                'ci' => '1234567',
                'ci_pendiente' => false,
                'nombre_completo' => 'Ana Pérez Rojas',
                'carrera' => 'Sistemas',
                'foto_url' => null,
            ],
            'grupo' => ['id_grupo' => 3, 'num_grupo' => '1'],
            'estado_habilitacion' => null,
            'ambiente_asignado' => ['id_ambiente' => self::ROOM_A, 'nro_aula' => '691A'],
            'antecedentes' => ['total' => 0, 'latest' => null],
            'ingreso_previo' => null,
        ], $overrides);
    }

    public function test_autoriza_al_estudiante_del_ambiente_de_control(): void
    {
        $result = $this->service->evaluate($this->record(), ['ci' => '1234567'], self::ROOM_A);

        $this->assertTrue($result['autorizado']);
        $this->assertSame(VerificationService::AUTHORIZED, $result['veredicto']);
        $this->assertSame(VerificationService::AUTHORIZED_MESSAGE, $result['motivo']);
        $this->assertNull($result['auxiliar']);
    }

    public function test_sin_ambiente_de_control_no_compara_el_ambiente(): void
    {
        $result = $this->service->evaluate($this->record(), [], null);

        $this->assertTrue($result['autorizado']);
    }

    public function test_ambiente_distinto_indica_el_que_corresponde(): void
    {
        $result = $this->service->evaluate($this->record(), [], self::ROOM_B);

        $this->assertFalse($result['autorizado']);
        $this->assertSame(VerificationService::WRONG_ROOM, $result['veredicto']);
        $this->assertSame('Corresponde al ambiente 691A', $result['motivo']);
    }

    public function test_sin_grupo_no_pertenece_al_examen(): void
    {
        $result = $this->service->evaluate($this->record(['grupo' => null]), [], self::ROOM_A);

        $this->assertSame(VerificationService::NOT_IN_EXAM, $result['veredicto']);
        $this->assertSame('No pertenece a los grupos de este examen', $result['motivo']);
    }

    public function test_no_habilitado(): void
    {
        $result = $this->service->evaluate(
            $this->record(['estado_habilitacion' => 'NO_HABILITADO']),
            [],
            self::ROOM_A
        );

        $this->assertSame(VerificationService::NOT_ENABLED, $result['veredicto']);
    }

    public function test_ci_distinto_no_coincide(): void
    {
        $result = $this->service->evaluate($this->record(), ['ci' => '7654321'], self::ROOM_A);

        $this->assertSame(VerificationService::CI_MISMATCH, $result['veredicto']);
        $this->assertSame('El CI no coincide con el registrado', $result['motivo']);
    }

    public function test_sin_ci_presentado_o_registrado_no_compara(): void
    {
        $withoutPresented = $this->service->evaluate($this->record(), ['ci' => null], self::ROOM_A);
        $withoutRegistered = $this->service->evaluate(
            $this->record(['estudiante' => array_replace($this->record()['estudiante'], ['ci' => null])]),
            ['ci' => '7654321'],
            self::ROOM_A
        );

        $this->assertTrue($withoutPresented['autorizado']);
        $this->assertTrue($withoutRegistered['autorizado']);
    }

    public function test_ingreso_previo_indica_hora_y_ambiente(): void
    {
        $result = $this->service->evaluate(
            $this->record(['ingreso_previo' => ['hora_ingreso' => '10:01:33', 'nro_aula' => '691A']]),
            [],
            self::ROOM_A
        );

        $this->assertSame(VerificationService::DUPLICATE, $result['veredicto']);
        $this->assertSame('Ya ingresó a las 10:01 por el 691A', $result['motivo']);
    }

    public function test_sin_ambiente_asignado(): void
    {
        $result = $this->service->evaluate($this->record(['ambiente_asignado' => null]), [], null);

        $this->assertSame(VerificationService::ROOM_NOT_ASSIGNED, $result['veredicto']);
    }

    public function test_respeta_el_orden_de_las_reglas(): void
    {
        $everythingWrong = $this->record([
            'estado_habilitacion' => 'NO_HABILITADO',
            'ingreso_previo' => ['hora_ingreso' => '10:01:00', 'nro_aula' => '691A'],
            'ambiente_asignado' => null,
        ]);

        $this->assertSame(
            VerificationService::NOT_ENABLED,
            $this->service->evaluate($everythingWrong, ['ci' => '7654321'], self::ROOM_B)['veredicto']
        );

        $everythingWrong['estado_habilitacion'] = null;
        $this->assertSame(
            VerificationService::CI_MISMATCH,
            $this->service->evaluate($everythingWrong, ['ci' => '7654321'], self::ROOM_B)['veredicto']
        );
        $this->assertSame(
            VerificationService::DUPLICATE,
            $this->service->evaluate($everythingWrong, [], self::ROOM_B)['veredicto']
        );
    }

    public function test_conserva_el_formato_antiguo_de_antecedentes(): void
    {
        $result = $this->service->evaluate(
            $this->record(['antecedentes' => [
                'cantidad' => 2,
                'detalles' => [['tipo' => 'Suplantación', 'fecha' => '2026-05-01']],
            ]]),
            [],
            self::ROOM_A
        );

        $this->assertSame(
            ['total' => 2, 'latest' => ['tipo_falta' => 'Suplantación', 'fecha_registro' => '2026-05-01']],
            $result['antecedentes']
        );
    }
}
