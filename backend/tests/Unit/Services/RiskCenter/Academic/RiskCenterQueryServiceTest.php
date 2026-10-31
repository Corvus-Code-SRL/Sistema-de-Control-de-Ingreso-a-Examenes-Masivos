<?php

namespace Tests\Unit\Services\RiskCenter\Academic;

use App\Services\RiskCenter\Academic\RiskCenterQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

/**
 * HU-046: summaryForStudent() es el contrato que consume el control de ingreso (HU-032) para avisar,
 * sin bloquear, que el estudiante tiene antecedentes en la Central de Riesgo.
 */
class RiskCenterQueryServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;

    private array $context;

    private RiskCenterQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->createReportingContext();
        $this->service = app(RiskCenterQueryService::class);
    }

    public function test_un_estudiante_sin_registros_tiene_total_cero_y_ningun_ultimo(): void
    {
        $this->assertSame(
            ['total' => 0, 'latest' => null],
            $this->service->summaryForStudent($this->context['id_estudiante'])
        );
    }

    public function test_un_estudiante_inexistente_tambien_devuelve_el_resumen_vacio(): void
    {
        $this->assertSame(['total' => 0, 'latest' => null], $this->service->summaryForStudent(2147483647));
    }

    public function test_con_un_registro_el_total_es_uno_y_el_ultimo_es_ese(): void
    {
        $record = $this->insertApprovedRecord($this->context, [], '2026-09-10 12:00:00+00');

        $summary = $this->service->summaryForStudent($this->context['id_estudiante']);

        $this->assertSame(1, $summary['total']);
        $this->assertSame($record['id_cr_estudiante'], $summary['latest']['id_cr_estudiante']);
        $this->assertSame($this->context['id_tipo_falta'], $summary['latest']['tipo_falta']['id_falta']);
        $this->assertSame(
            DB::table('tipo_falta')->where('id_falta', $this->context['id_tipo_falta'])->value('nombre'),
            $summary['latest']['tipo_falta']['nombre']
        );
        $this->assertInstanceOf(Carbon::class, $summary['latest']['fecha_registro']);
        $this->assertSame('2026-09-10T12:00:00+00:00', $summary['latest']['fecha_registro']->toIso8601String());
    }

    public function test_con_varios_registros_cuenta_todos_y_el_ultimo_es_el_mas_reciente(): void
    {
        $copy = $this->insertFaultType('Copia');
        $this->insertApprovedRecord($this->context, [], '2026-09-10 12:00:00+00');
        $newest = $this->insertApprovedRecord($this->context, ['id_tipo_falta' => $copy], '2026-09-22 12:00:00+00');
        $this->insertApprovedRecord($this->context, [], '2026-09-15 12:00:00+00');

        $summary = $this->service->summaryForStudent($this->context['id_estudiante']);

        $this->assertSame(3, $summary['total']);
        $this->assertSame($newest['id_cr_estudiante'], $summary['latest']['id_cr_estudiante']);
        $this->assertSame(['id_falta' => $copy, 'nombre' => 'Copia'], $summary['latest']['tipo_falta']);
    }

    public function test_con_la_misma_fecha_el_ultimo_es_el_de_mayor_id(): void
    {
        $this->insertApprovedRecord($this->context, [], '2026-09-10 12:00:00+00');
        $second = $this->insertApprovedRecord($this->context, [], '2026-09-10 12:00:00+00');

        $this->assertSame(
            $second['id_cr_estudiante'],
            $this->service->summaryForStudent($this->context['id_estudiante'])['latest']['id_cr_estudiante']
        );
    }

    public function test_los_registros_de_otros_estudiantes_no_cuentan(): void
    {
        $other = $this->insertStudent('Otro', 'Estudiante', null, '202300002');
        $this->insertApprovedRecord($this->context, ['id_estudiante' => $other]);
        $mine = $this->insertApprovedRecord($this->context, [], '2026-09-10 12:00:00+00');

        $summary = $this->service->summaryForStudent($this->context['id_estudiante']);

        $this->assertSame(1, $summary['total']);
        $this->assertSame($mine['id_cr_estudiante'], $summary['latest']['id_cr_estudiante']);
    }

    public function test_los_reportes_que_no_estan_aprobados_no_cuentan(): void
    {
        $this->insertStudentReport($this->context);

        $this->assertSame(0, $this->service->summaryForStudent($this->context['id_estudiante'])['total']);
    }

    public function test_snapshot_limita_antecedentes_y_desempata_por_id_de_central(): void
    {
        $otherId = $this->insertStudent('Ajeno', 'Examen', null, '202300003');
        $this->insertApprovedRecord($this->context, ['id_estudiante' => $otherId]);
        $date = '2026-09-10 12:00:00+00';
        $approved = [
            'estado' => 'APROBADO',
            'fecha_revision' => $date,
            'id_usuario_revisor' => $this->context['id_usuario'],
        ];
        $olderReportId = $this->insertStudentReport($this->context, $approved + [
            'id_tipo_falta' => $this->insertFaultType('Mayor id de reporte'),
        ]);
        $newerReportId = $this->insertStudentReport($this->context, $approved + [
            'id_tipo_falta' => $this->insertFaultType('Mayor id de central'),
        ]);
        DB::table('central_riesgo')->insert([
            'id_reporte_est' => $newerReportId,
            'id_estudiante' => $this->context['id_estudiante'],
            'fecha_registro' => $date,
        ]);
        DB::table('central_riesgo')->insert([
            'id_reporte_est' => $olderReportId,
            'id_estudiante' => $this->context['id_estudiante'],
            'fecha_registro' => $date,
        ]);

        $summaries = $this->service->summariesForSnapshot([$this->context['id_estudiante']]);

        $this->assertSame([$this->context['id_estudiante']], array_keys($summaries));
        $this->assertSame(2, $summaries[$this->context['id_estudiante']]['total']);
        $this->assertSame(
            'Mayor id de reporte',
            $summaries[$this->context['id_estudiante']]['latest']['tipo_falta']
        );
        $this->assertSame('2026-09-10', $summaries[$this->context['id_estudiante']]['latest']['fecha_registro']);
        $this->assertSame([], $this->service->summariesForSnapshot([]));
    }

    public function test_se_resuelve_en_una_sola_consulta_sin_importar_cuantos_registros_haya(): void
    {
        foreach (range(1, 5) as $unused) {
            $this->insertApprovedRecord($this->context, ['id_tipo_falta' => $this->insertFaultType('Falta ' . uniqid())]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->summaryForStudent($this->context['id_estudiante']);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
    }
}
