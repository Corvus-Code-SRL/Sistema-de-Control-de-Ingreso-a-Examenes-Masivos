<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Exam;
use App\Models\Role;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
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
 * HU-062: tras rectificar, la consulta de HU-046 y el aviso de antecedentes del control de ingreso
 * (summaryForStudent y summariesForSnapshot) leen solo la versión vigente.
 */
class RectifiedRecordReadsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use SeedsRectifications;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private array $context;

    private int $recordId;

    private int $newTypeId;

    private int $oldTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seedSecurityAccounts();
        $this->context = $this->createReportingContext();
        $this->oldTypeId = $this->context['id_tipo_falta'];
        $this->newTypeId = $this->insertFaultType('Suplantación ' . uniqid());
        $this->recordId = $this->insertApprovedRecord($this->context, ['descripcion' => 'Descripción original.'])
            ['id_cr_estudiante'];
    }

    private function rectifyToNewType(): void
    {
        $this->rectify($this->recordId, [
            'id_tipo_falta' => $this->newTypeId,
            'descripcion' => 'Descripción vigente.',
        ])->assertCreated();
    }

    public function test_sin_rectificar_la_consulta_muestra_los_valores_del_reporte(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo')
            ->assertOk()
            ->assertJsonPath('data.0.tipo_falta.id_falta', $this->oldTypeId);
        $this->getJson('/api/central-riesgo/' . $this->recordId)
            ->assertOk()
            ->assertJsonPath('data.tipo_falta.id_falta', $this->oldTypeId)
            ->assertJsonPath('data.descripcion', 'Descripción original.');
    }

    public function test_el_listado_de_hu_046_muestra_solo_el_tipo_vigente(): void
    {
        $this->rectifyToNewType();
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/central-riesgo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo_falta.id_falta', $this->newTypeId)
            ->assertJsonPath('data.0.tipo_falta.nombre', DB::table('tipo_falta')
                ->where('id_falta', $this->newTypeId)->value('nombre'));
    }

    public function test_el_filtro_por_tipo_usa_el_tipo_vigente(): void
    {
        $this->rectifyToNewType();
        $this->actAs($this->createAccount(Role::ADMINISTRADOR));

        $this->getJson('/api/central-riesgo?tipo=' . $this->newTypeId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_cr_estudiante', $this->recordId);
        $this->getJson('/api/central-riesgo?tipo=' . $this->oldTypeId)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_el_filtro_por_tipo_sigue_usando_el_reporte_si_nunca_se_rectifico(): void
    {
        $other = $this->insertApprovedRecord($this->context);
        $this->rectifyToNewType();
        $this->actAs($this->createAccount(Role::DOCENTE));

        $ids = $this->getJson('/api/central-riesgo?tipo=' . $this->oldTypeId)->assertOk()->json('data.*.id_cr_estudiante');

        $this->assertSame([$other['id_cr_estudiante']], $ids);
    }

    public function test_el_detalle_de_hu_046_muestra_tipo_y_descripcion_vigentes_y_conserva_lo_fijo(): void
    {
        $before = $this->getJson('/api/central-riesgo/' . $this->recordId)->assertOk()->json('data');
        $this->rectifyToNewType();

        $after = $this->getJson('/api/central-riesgo/' . $this->recordId)->assertOk()->json('data');

        $this->assertSame($this->newTypeId, $after['tipo_falta']['id_falta']);
        $this->assertSame('Descripción vigente.', $after['descripcion']);
        foreach (['estudiante', 'examen', 'ambiente', 'reportante', 'aprobador', 'observacion', 'fecha_registro', 'evidencia'] as $key) {
            $this->assertSame($before[$key], $after[$key], $key);
        }
    }

    public function test_las_respuestas_de_hu_046_no_exponen_el_estado_de_rectificacion(): void
    {
        $this->rectifyToNewType();

        $list = $this->getJson('/api/central-riesgo')->assertOk()->json('data.0');
        $detail = $this->getJson('/api/central-riesgo/' . $this->recordId)->assertOk()->json('data');

        $this->assertSame(
            ['id_cr_estudiante', 'estudiante', 'tipo_falta', 'examen', 'fecha_registro'],
            array_keys($list)
        );
        foreach (['estado', 'historial', 'numero_rectificaciones', 'ultima_rectificacion', 'version_vigente'] as $key) {
            $this->assertArrayNotHasKey($key, $list);
            $this->assertArrayNotHasKey($key, $detail);
        }
    }

    public function test_el_orden_y_la_fecha_del_registro_no_cambian_con_la_rectificacion(): void
    {
        $newer = $this->insertApprovedRecord($this->context, [], '2026-10-20 12:00:00+00');
        $this->rectifyToNewType();

        $ids = $this->getJson('/api/central-riesgo')->assertOk()->json('data.*.id_cr_estudiante');

        $this->assertSame([$newer['id_cr_estudiante'], $this->recordId], $ids);
        $this->assertSame(
            '2026-10-02T12:00:00+00:00',
            $this->getJson('/api/central-riesgo/' . $this->recordId)->json('data.fecha_registro')
        );
    }

    public function test_summary_for_student_usa_la_version_vigente(): void
    {
        $this->rectifyToNewType();

        $summary = app(RiskCenterQueryService::class)->summaryForStudent($this->context['id_estudiante']);

        $this->assertSame(1, $summary['total']);
        $this->assertSame($this->newTypeId, $summary['latest']['tipo_falta']['id_falta']);
        $this->assertSame(
            DB::table('tipo_falta')->where('id_falta', $this->newTypeId)->value('nombre'),
            $summary['latest']['tipo_falta']['nombre']
        );
        $this->assertSame($this->recordId, $summary['latest']['id_cr_estudiante']);
    }

    public function test_summaries_for_snapshot_usa_la_version_vigente_y_no_cambia_el_total(): void
    {
        $this->insertApprovedRecord($this->context, [], '2026-09-01 12:00:00+00');
        $this->rectifyToNewType();
        $newTypeName = DB::table('tipo_falta')->where('id_falta', $this->newTypeId)->value('nombre');

        $summaries = app(RiskCenterQueryService::class)->summariesForSnapshot([$this->context['id_estudiante']]);

        $this->assertSame(
            [$this->context['id_estudiante'] => [
                'total' => 2,
                'latest' => ['tipo_falta' => $newTypeName, 'fecha_registro' => '2026-10-02'],
            ]],
            $summaries
        );
    }

    public function test_una_nueva_rectificacion_reemplaza_a_la_anterior_en_los_resumenes(): void
    {
        $this->rectifyToNewType();
        $thirdType = $this->insertFaultType('Plagio ' . uniqid());
        $this->rectify($this->recordId, ['id_tipo_falta' => $thirdType, 'version_base' => 2])->assertCreated();

        $summary = app(RiskCenterQueryService::class)->summaryForStudent($this->context['id_estudiante']);

        $this->assertSame($thirdType, $summary['latest']['tipo_falta']['id_falta']);
    }

    public function test_actualiza_los_antecedentes_del_snapshot_activo(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam([], [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $studentId = (int) DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)->value('id_estudiante');
        $recordId = $this->recordOfStudent($studentId);
        DB::table('examen')->where('id_examen', $exam->id_examen)->update(['estado' => Exam::EN_INGRESO]);
        app(RoomAssignmentService::class)->prepare($exam->id_examen);
        $snapshots = app(EntryControlSnapshotService::class);
        $examId = (int) $exam->id_examen;

        $this->assertSame(1, $snapshots->studentById($examId, $studentId)['antecedentes']['total']);

        $this->actAsUserId($this->administratorId);
        $this->rectify($recordId, ['id_tipo_falta' => $this->newTypeId])->assertCreated();

        $background = $snapshots->studentById($examId, $studentId)['antecedentes'];
        $this->assertSame(1, $background['total']);
        $this->assertSame(
            DB::table('tipo_falta')->where('id_falta', $this->newTypeId)->value('nombre'),
            $background['latest']['tipo_falta']
        );
    }

    public function test_sin_snapshot_activo_no_actualiza_nada(): void
    {
        $this->assertSame(0, app(EntryControlSnapshotService::class)->refreshBackground($this->context['id_estudiante']));
    }

    /** Un registro aprobado para un estudiante de la nómina del examen. */
    private function recordOfStudent(int $studentId): int
    {
        $context = array_merge($this->context, ['id_estudiante' => $studentId]);

        return $this->insertApprovedRecord($context)['id_cr_estudiante'];
    }
}
