<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Exam;
use App\Models\RiskCenter;
use App\Models\Role;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\EntryControl\VerificationService;
use App\Services\RiskCenter\Academic\RiskCenterQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsExamCatalog;
use Tests\Concerns\SeedsRectifications;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: un registro rectificado fue retirado de la Central y ya no cuenta como antecedente. La consulta de
 * HU-046 (listado, filtros, detalle y evidencia) y el aviso de antecedentes del control de ingreso (resumen por
 * estudiante, resumen del snapshot y snapshot abierto) lo ignoran.
 */
class RectifiedRecordsAreIgnoredTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    private int $activeId;

    private int $voidedId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->activeId = $this->insertApprovedRecord($this->context, [], '2026-09-01 12:00:00+00')['id_cr_estudiante'];
        $this->voidedId = $this->insertApprovedRecord(
            $this->context,
            ['descripcion' => 'Descripción del retirado.'],
            '2026-10-02 12:00:00+00'
        )['id_cr_estudiante'];
    }

    public function test_el_listado_de_hu_046_no_incluye_los_rectificados(): void
    {
        $this->voidRecord($this->voidedId);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $response = $this->getJson('/api/central-riesgo')->assertOk();

        $this->assertSame([$this->activeId], $response->json('data.*.id_cr_estudiante'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_el_filtro_por_tipo_y_la_busqueda_no_devuelven_rectificados(): void
    {
        $this->voidRecord($this->voidedId);
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $byType = $this->getJson('/api/central-riesgo?tipo=' . $this->context['id_tipo_falta'])->assertOk();
        $bySearch = $this->getJson('/api/central-riesgo?q=' . urlencode('Nombre Apellido'))->assertOk();

        $this->assertSame([$this->activeId], $byType->json('data.*.id_cr_estudiante'));
        $this->assertSame([$this->activeId], $bySearch->json('data.*.id_cr_estudiante'));
    }

    public function test_un_estudiante_con_todos_sus_registros_rectificados_no_aparece_en_el_listado(): void
    {
        $this->voidRecord($this->activeId);
        $this->voidRecord($this->voidedId);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_el_detalle_de_hu_046_de_un_rectificado_responde_404_y_el_activo_sigue_visible(): void
    {
        $this->voidRecord($this->voidedId);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo/' . $this->voidedId)->assertNotFound();
        $this->getJson('/api/central-riesgo/' . $this->activeId)->assertOk();
    }

    public function test_la_evidencia_de_un_rectificado_responde_404_aunque_el_archivo_exista(): void
    {
        Storage::disk('public')->put('evidencias/reportes/retirado.jpg', 'datos');
        $reportId = DB::table('central_riesgo')->where('id_cr_estudiante', $this->voidedId)->value('id_reporte_est');
        DB::table('reporte_estudiante')->where('id_reporte_est', $reportId)->update([
            'evidencia_url' => 'http://localhost/storage/evidencias/reportes/retirado.jpg',
            'evidencia_public_id' => 'evidencias/reportes/retirado.jpg',
        ]);
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->get('/api/central-riesgo/' . $this->voidedId . '/evidencia')->assertOk();
        $this->voidRecord($this->voidedId);

        $this->getJson('/api/central-riesgo/' . $this->voidedId . '/evidencia')->assertNotFound();
    }

    public function test_las_respuestas_de_hu_046_conservan_sus_claves(): void
    {
        $this->voidRecord($this->voidedId);
        $this->actAs($this->createAccount(Role::DOCENTE));

        $list = $this->getJson('/api/central-riesgo')->assertOk()->json('data.0');
        $detail = $this->getJson('/api/central-riesgo/' . $this->activeId)->assertOk()->json('data');

        $this->assertSame(
            ['id_cr_estudiante', 'estudiante', 'tipo_falta', 'examen', 'fecha_registro'],
            array_keys($list)
        );
        foreach (['rectificado_en', 'rectificacion', 'motivo_rectificacion', 'estado', 'historial'] as $key) {
            $this->assertArrayNotHasKey($key, $list);
            $this->assertArrayNotHasKey($key, $detail);
        }
    }

    public function test_summary_for_student_no_cuenta_los_rectificados(): void
    {
        $service = app(RiskCenterQueryService::class);
        $this->assertSame(2, $service->summaryForStudent($this->context['id_estudiante'])['total']);

        $this->voidRecord($this->voidedId);

        $summary = $service->summaryForStudent($this->context['id_estudiante']);
        $this->assertSame(1, $summary['total']);
        $this->assertSame($this->activeId, $summary['latest']['id_cr_estudiante']);
    }

    public function test_summary_for_student_sin_registros_activos_no_tiene_antecedentes(): void
    {
        $this->voidRecord($this->activeId);
        $this->voidRecord($this->voidedId);

        $this->assertSame(
            ['total' => 0, 'latest' => null],
            app(RiskCenterQueryService::class)->summaryForStudent($this->context['id_estudiante'])
        );
    }

    public function test_summaries_for_snapshot_no_cuenta_los_rectificados(): void
    {
        $this->voidRecord($this->voidedId);

        $summaries = app(RiskCenterQueryService::class)->summariesForSnapshot([$this->context['id_estudiante']]);

        $this->assertSame(1, $summaries[$this->context['id_estudiante']]['total']);
        $this->assertSame('2026-09-01', $summaries[$this->context['id_estudiante']]['latest']['fecha_registro']);
    }

    public function test_summaries_for_snapshot_omite_al_estudiante_sin_registros_activos(): void
    {
        $this->voidRecord($this->activeId);
        $this->voidRecord($this->voidedId);

        $this->assertSame(
            [],
            app(RiskCenterQueryService::class)->summariesForSnapshot([$this->context['id_estudiante']])
        );
    }

    public function test_with_background_del_ingreso_tardio_ignora_los_rectificados(): void
    {
        $record = ['grupo' => null, 'estudiante' => ['id_estudiante' => $this->context['id_estudiante']]];

        $this->voidRecord($this->voidedId);
        $one = app(VerificationService::class)->withBackground($record);
        $this->voidRecord($this->activeId);
        $none = app(VerificationService::class)->withBackground($record);

        $this->assertSame(1, $one['antecedentes']['total']);
        $this->assertSame('2026-09-01', $one['antecedentes']['latest']['fecha_registro']);
        $this->assertSame(['total' => 0, 'latest' => null], $none['antecedentes']);
    }

    public function test_las_relaciones_eloquent_devuelven_solo_registros_activos(): void
    {
        $this->voidRecord($this->voidedId);

        $student = \App\Models\Student::query()->find($this->context['id_estudiante']);
        $report = \App\Models\StudentReport::query()
            ->where('id_reporte_est', RiskCenter::withVoided()->find($this->voidedId)->id_reporte_est)
            ->first();

        $this->assertSame([$this->activeId], $student->riskCenters()->pluck('id_cr_estudiante')->all());
        $this->assertNull($report->riskCenter);
        $this->assertNotNull($report->voidedRiskCenter);
        $this->assertCount(2, RiskCenter::withVoided()->where('id_estudiante', $student->id_estudiante)->get());
    }

    public function test_el_snapshot_abierto_deja_de_avisar_el_antecedente_tras_rectificar(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam([], [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $studentId = (int) DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->value('id_estudiante');
        $recordId = $this->insertApprovedRecord(array_merge($this->context, ['id_estudiante' => $studentId]))
            ['id_cr_estudiante'];
        $this->putExamInState((int) $exam->id_examen, 'INGRESO_ABIERTO');
        app(RoomAssignmentService::class)->prepare($exam->id_examen);
        $snapshots = app(EntryControlSnapshotService::class);
        $examId = (int) $exam->id_examen;

        $this->assertSame(1, $snapshots->studentById($examId, $studentId)['antecedentes']['total']);

        $this->actAsUserId($this->administratorId);
        $this->rectify($recordId)->assertOk();

        $this->assertSame(
            ['total' => 0, 'latest' => null],
            $snapshots->studentById($examId, $studentId)['antecedentes']
        );
    }

    public function test_sin_snapshot_activo_no_actualiza_nada(): void
    {
        $refreshed = app(EntryControlSnapshotService::class)->refreshBackground($this->context['id_estudiante']);

        $this->assertSame(0, $refreshed);
    }
}
