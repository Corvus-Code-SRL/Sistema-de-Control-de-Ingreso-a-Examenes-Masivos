<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Services\EntryControl\EntryRoomPolicy;
use App\Services\EntryControl\RoomAssignmentService;
use App\Support\Exams\EntryRoomWarning;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

/** HU-031 CA-2 y CA-11 en HU-032: la distribución y la capacidad avisan, nunca rechazan. */
class OptionalDistributionEntryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_sin_distribucion_ingresa_por_cualquier_ambiente_sin_aviso(): void
    {
        $exam = $this->openExam(false);
        [$student] = $this->distributionStudentIds($exam);

        $this->verify($exam, $student, $this->otraAulaId)->assertOk()
            ->assertJsonPath('data.autorizado', true)
            ->assertJsonPath('data.avisos_ambiente', []);
        $this->confirm($exam, $student, $this->otraAulaId)->assertCreated()
            ->assertJsonPath('data.fuera_de_ambiente', false)
            ->assertJsonPath('data.id_ambiente_planificado', null);
    }

    public function test_otro_ambiente_avisa_y_sin_confirmar_no_registra_ni_crea_intento(): void
    {
        $exam = $this->openExam();
        [$student] = $this->distributionStudentIds($exam);

        $this->verify($exam, $student, $this->otraAulaId)->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.avisos_ambiente.0.tipo', EntryRoomWarning::OUT_OF_ROOM)
            ->assertJsonPath('data.avisos_ambiente.0.mensaje', 'Este estudiante tiene asignado el ambiente 691A');
        $this->confirm($exam, $student, $this->otraAulaId)->assertStatus(409)
            ->assertJsonPath('veredicto', EntryRoomWarning::VERDICT)
            ->assertJsonPath('avisos_ambiente.0.ambiente_asignado.id_ambiente', $this->aulaId);

        $this->assertDatabaseMissing('examen_estudiante', [
            'id_examen' => $exam->id_examen, 'id_estudiante' => $student, 'estado_ingreso' => 'INGRESO',
        ]);
        $this->assertSame(0, DB::table('intento_ingreso')->where('id_examen', $exam->id_examen)->count());
    }

    public function test_confirmado_guarda_ambiente_real_marca_y_bitacora_sin_incidencia(): void
    {
        $exam = $this->openExam();
        [$student] = $this->distributionStudentIds($exam);

        $this->confirm($exam, $student, $this->otraAulaId, true)->assertCreated()
            ->assertJsonPath('data.id_ambiente', $this->otraAulaId)
            ->assertJsonPath('data.id_ambiente_planificado', $this->aulaId)
            ->assertJsonPath('data.fuera_de_ambiente', true);

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $student,
            'estado_ingreso' => 'INGRESO',
            'id_ambiente' => $this->otraAulaId,
            'id_ambiente_planificado' => $this->aulaId,
        ]);
        $log = $this->auditRow();
        $this->assertSame($this->aulaId, $log['id_ambiente_asignado']);
        $this->assertSame($this->otraAulaId, $log['id_ambiente_real']);
        $this->assertSame(0, DB::table('intento_ingreso')->where('id_examen', $exam->id_examen)->count());
        $this->assertSame(0, DB::table('reporte_estudiante')->where('id_examen', $exam->id_examen)->count());
    }

    public function test_en_su_ambiente_no_avisa_ni_marca(): void
    {
        $exam = $this->openExam();
        [$student] = $this->distributionStudentIds($exam);

        $this->confirm($exam, $student, $this->aulaId)->assertCreated()
            ->assertJsonPath('data.fuera_de_ambiente', false);
        $this->assertNull($this->auditRowOrNull());
    }

    public function test_ambiente_lleno_avisa_y_deja_registrar_al_confirmar(): void
    {
        $exam = $this->openExam(false, [1, 3]);
        [$first, $second] = $this->distributionStudentIds($exam);
        $this->confirm($exam, $first, $this->aulaId)->assertCreated();

        $this->confirm($exam, $second, $this->aulaId)->assertStatus(409)
            ->assertJsonPath('avisos_ambiente.0.tipo', EntryRoomWarning::ROOM_FULL)
            ->assertJsonPath('avisos_ambiente.0.mensaje', 'El ambiente 691A ya está lleno (1 de 1)');
        $this->confirm($exam, $second, $this->aulaId, true)->assertCreated()
            ->assertJsonPath('data.fuera_de_ambiente', false);
        $this->assertSame([EntryRoomWarning::ROOM_FULL], $this->auditRow()['avisos']);
    }

    public function test_excepcional_avisa_si_ingresa_por_otro_ambiente_que_el_elegido_al_registrarlo(): void
    {
        $exam = $this->openExam(false, [3, 3]);
        $room = ['id_ambiente' => $this->otraAulaId, 'nro_aula' => '692B'];
        $policy = app(EntryRoomPolicy::class);
        $record = ['autorizado' => true, 'ambiente_asignado' => $room, 'es_excepcional' => true];

        $decorated = $policy->decorate((int) $exam->id_examen, $record, $this->aulaId);
        $this->assertSame(
            ['Este estudiante tiene asignado el ambiente 692B'],
            array_column($decorated['avisos_ambiente'], 'mensaje')
        );
        $this->assertSame([], $policy->decorate((int) $exam->id_examen, $record, $this->otraAulaId)['avisos_ambiente']);

        $resolution = $policy->resolve((int) $exam->id_examen, $record, $this->otraAulaId, false);
        $this->assertSame([], $resolution['avisos']);
        $this->expectException(\App\Exceptions\EntryControl\EntryRoomWarningException::class);
        $policy->resolve((int) $exam->id_examen, $record, $this->aulaId, false);
    }

    /** @param list<int> $capacities */
    private function openExam(bool $withDistribution = true, array $capacities = [3, 3]): Exam
    {
        $exam = $this->distributionExam(4, $capacities);

        if ($withDistribution) {
            $this->saveDistribution($exam)->assertOk();
        }

        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        return $exam->fresh();
    }

    private function verify(Exam $exam, int $student, int $room): TestResponse
    {
        $sis = DB::table('estudiante')->where('id_estudiante', $student)->value('cod_sis');

        return $this->postJson("/api/control-ingreso/examenes/{$exam->id_examen}/verificar", [
            'cod_sis' => $sis, 'id_ambiente' => $room,
        ]);
    }

    private function confirm(Exam $exam, int $student, int $room, bool $confirmed = false): TestResponse
    {
        return $this->postJson("/api/control-ingreso/examenes/{$exam->id_examen}/confirmar-ingreso", [
            'id_estudiante' => $student, 'id_ambiente' => $room,
        ] + ($confirmed ? ['confirmar_aviso_ambiente' => true] : []));
    }

    /** @return array{id_ambiente_asignado:int|null, id_ambiente_real:int, avisos:list<string>} */
    private function auditRow(): array
    {
        $row = $this->auditRowOrNull();
        $this->assertNotNull($row);

        return $row;
    }

    /** @return array{id_ambiente_asignado:int|null, id_ambiente_real:int, avisos:list<string>}|null */
    private function auditRowOrNull(): ?array
    {
        $value = DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', EntryRoomPolicy::AUDIT_ACTION)
            ->orderByDesc('log.id_log')->value('log.nuevo_valor');

        return $value === null ? null : json_decode($value, true);
    }
}
