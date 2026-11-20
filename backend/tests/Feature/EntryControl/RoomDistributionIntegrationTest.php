<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\EntryOpeningException;
use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\EntryControl\VerificationService;
use App\Services\Exams\Distribution\DistributionRosterQuery;
use App\Services\Exams\Distribution\RoomDistributionReader;
use App\Services\Exams\ExamInProgressQueryService;
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

    public function test_control_valida_la_distribucion_guardada_y_coincide_con_examen_en_curso(): void
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
        $this->assertSame(VerificationService::WRONG_ROOM, $verification->verify((int) $exam->id_examen, [
            'cod_sis' => $record['estudiante']['cod_sis'], 'id_ambiente' => $this->aulaId,
        ], $teacher)['veredicto']);
        $this->assertSame(VerificationService::AUTHORIZED, $verification->verify((int) $exam->id_examen, [
            'cod_sis' => $record['estudiante']['cod_sis'], 'id_ambiente' => $this->otraAulaId,
        ], $teacher)['veredicto']);
        $this->getJson($this->distributionUrl($exam))->assertOk()->assertJsonPath('data.editable', false);
    }

    public function test_nuevo_habilitado_recibe_lugar_al_abrir_sin_modificar_lo_guardado(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        $before = app(DistributionRosterQuery::class)->savedRows((int) $exam->id_examen);
        $newId = $this->addDistributionStudent($exam);
        $this->getJson($this->distributionUrl($exam))->assertOk()
            ->assertJsonPath('data.estudiantes.4.id_ambiente', null);

        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $assigned = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        $this->assertArrayHasKey($newId, $assigned);
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
            app(VerificationService::class)->evaluate($record, [], $this->aulaId)['veredicto']);
    }

    public function test_excedente_conserva_el_contrato_sin_ambiente_y_no_redistribuye_todo(): void
    {
        $exam = $this->distributionExam(4, [2, 2]);
        $this->saveDistribution($exam)->assertOk();
        $saved = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        $newId = $this->addDistributionStudent($exam);
        $snapshots = app(EntryControlSnapshotService::class);
        // stage() se prueba directamente: prepare() ya rechaza esta capacidad antes de HU-031.
        $token = $snapshots->stage($exam);
        $snapshots->activate((int) $exam->id_examen, $token);
        $this->assertSame($saved, app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
        $this->assertCoherentRooms($exam, $saved);
        $record = $snapshots->studentById((int) $exam->id_examen, $newId);
        $result = app(VerificationService::class)->evaluate($record, [], $this->aulaId);
        $this->assertNull($record['ambiente_asignado']);
        $this->assertSame(VerificationService::ROOM_NOT_ASSIGNED, $result['veredicto']);
        $this->assertFalse($result['autorizado']);
    }

    public function test_apertura_conserva_su_rechazo_preexistente_por_capacidad(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        DB::table('ambiente')->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])
            ->update(['capacidad' => 1]);
        $this->expectException(EntryOpeningException::class);
        $this->expectExceptionMessage('Capacidad 2 menor que la nómina de 4.');

        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
    }

    public function test_excepcional_conserva_ambiente_operativo_en_snapshot_y_examen_en_curso(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        [$special] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $special, 'CASO ESPECIAL');
        DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $special)->update(['id_ambiente' => $this->otraAulaId]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $assigned = app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen);
        $this->assertSame($this->otraAulaId, $assigned[$special]['id_ambiente']);
        $this->assertCoherentRooms($exam, $assigned);
        $this->assertDatabaseHas('distribucion_ambiente', [
            'id_examen' => $exam->id_examen, 'id_estudiante' => $special, 'id_ambiente' => $this->aulaId,
        ]);
    }

    public function test_sin_distribucion_conserva_calculo_anterior_incluido_no_habilitado_a4(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        [$disabled] = $this->distributionStudentIds($exam);
        $this->setDistributionEligibility($exam, $disabled, 'NO_HABILITADO');
        $expected = EntryControlSnapshotService::assignRooms(
            $this->distributionStudentIds($exam), app(DistributionRosterQuery::class)->rooms((int) $exam->id_examen)
        );
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $this->assertNull(app(RoomDistributionReader::class)->assignedRooms((int) $exam->id_examen));
        $this->assertCoherentRooms($exam, $expected);
        $this->assertArrayHasKey($disabled, $expected);
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
