<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\EntryControl\VerificationService;
use App\Services\Exams\Distribution\DistributionRosterQuery;
use App\Services\Exams\Distribution\RoomDistributionReader;
use App\Services\Exams\ExamInProgressQueryService;
use App\Support\Exams\EntryRoomWarning;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

class RoomDistributionIntegrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_control_avisa_con_la_distribucion_guardada_y_coincide_con_examen_en_curso(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        $saved = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $this->assertCoherentRooms($exam, $saved);

        $studentId = $this->distributionStudentIds($exam)[2];
        $record = app(EntryControlSnapshotService::class)->studentById((int) $exam->id_examen, $studentId);
        $verification = app(VerificationService::class);
        $teacher = User::findOrFail($this->docenteId);
        $elsewhere = $verification->verify((int) $exam->id_examen, [
            'cod_sis' => $record['estudiante']['cod_sis'], 'id_ambiente' => $this->aulaId,
        ], $teacher);
        $this->assertSame(VerificationService::AUTHORIZED, $elsewhere['veredicto']);
        $this->assertSame(EntryRoomWarning::OUT_OF_ROOM, $elsewhere['avisos_ambiente'][0]['tipo']);
        $this->assertSame([], $verification->verify((int) $exam->id_examen, [
            'cod_sis' => $record['estudiante']['cod_sis'], 'id_ambiente' => $this->otraAulaId,
        ], $teacher)['avisos_ambiente']);
        $this->getJson($this->distributionUrl($exam))->assertOk()->assertJsonPath('data.editable', false);
    }

    public function test_nuevo_habilitado_queda_sin_ambiente_al_abrir_sin_modificar_lo_guardado(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        $before = app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen);
        $newId = $this->addDistributionStudent($exam);
        $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.4.id_ambiente', null);

        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $assigned = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        $this->assertArrayNotHasKey($newId, $assigned);
        $this->assertCoherentRooms($exam, $assigned);
        $this->assertSame($before, app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen));
    }

    public function test_deshabilitados_y_ambientes_retirados_se_reflejan_en_ambos_consumidores(): void
    {
        $exam = $this->distributionExam(4, [4, 4]);
        $this->saveDistribution($exam)->assertOk();
        [$disabled] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $disabled, 'NO_HABILITADO');
        DB::table('examen_ambiente')->where('id_examen', $exam->id_examen)
            ->where('id_ambiente', $this->otraAulaId)->delete();
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $assigned = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        $this->assertArrayNotHasKey($disabled, $assigned);
        $this->assertCoherentRooms($exam, $assigned);
        $record = app(EntryControlSnapshotService::class)->studentById((int) $exam->id_examen, $disabled);
        $this->assertSame(VerificationService::NOT_ENABLED,
            app(VerificationService::class)->evaluate($record, [])['veredicto']);
    }

    public function test_excedente_sin_ambiente_puede_ingresar_sin_aviso(): void
    {
        $exam = $this->distributionExam(4, [2, 2]);
        $this->saveDistribution($exam)->assertOk();
        $newId = $this->addDistributionStudent($exam);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $record = app(EntryControlSnapshotService::class)->studentById((int) $exam->id_examen, $newId);

        $result = app(VerificationService::class)->evaluate($record, []);

        $this->assertNull($record['ambiente_asignado']);
        $this->assertTrue($result['autorizado']);
    }

    public function test_apertura_ya_no_falla_por_capacidad(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        DB::table('ambiente')->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])
            ->update(['capacidad' => 1]);

        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $this->assertTrue(app(RoomAssignmentService::class)->hasPreparedSnapshot((int) $exam->id_examen));
    }

    public function test_sin_distribucion_no_hay_aula_implicita(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $this->assertSame([], app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
        $this->assertCoherentRooms($exam, []);
    }

    /** @param array<int, array{id_ambiente:int, nro_aula:string}> $expected */
    private function assertCoherentRooms(Exam $exam, array $expected): void
    {
        $exam->forceFill(['estado' => Exam::EN_CURSO])->save();
        $inProgress = app(ExamInProgressQueryService::class)->show($exam->fresh(), User::findOrFail($this->docenteId));
        $students = array_column($inProgress['estudiantes'], null, 'id_estudiante');
        $snapshots = app(EntryControlSnapshotService::class);

        foreach ($this->distributionStudentIds($exam) as $id) {
            $snapshot = $snapshots->studentById((int) $exam->id_examen, $id);
            $record = $snapshots->recordFor($exam, $id);
            $this->assertSame($expected[$id] ?? null, $snapshot['ambiente_asignado']);
            $this->assertSame($snapshot['ambiente_asignado'], $record['ambiente_asignado']);
            $this->assertSame($expected[$id]['id_ambiente'] ?? null, $students[$id]['id_ambiente']);
            $this->assertSame($expected[$id]['nro_aula'] ?? null, $students[$id]['ambiente']);
        }
    }
}
