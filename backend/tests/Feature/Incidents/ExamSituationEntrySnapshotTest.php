<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

/** Un cambio de ambiente durante «En ingreso» debe verse en el control de ingreso (Redis) de ambas aulas. */
class ExamSituationEntrySnapshotTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
        $this->situationExam->update(['estado' => Exam::EN_INGRESO]);
    }

    private function entryUrl(string $action): string
    {
        return "/api/control-ingreso/examenes/{$this->situationExam->id_examen}/{$action}";
    }

    private function roomNumber(int $roomId): string
    {
        return (string) DB::table('ambiente')->where('id_ambiente', $roomId)->value('nro_aula');
    }

    /** Deja un auxiliar en el aula de destino y abre el control, como lo hace el job de apertura. */
    private function openEntryControlWithDestinationAssistant(): User
    {
        $this->actAsSituationAssistant($this->otraAulaId);
        $assistant = User::query()->findOrFail($this->otroDocenteId);
        app(RoomAssignmentService::class)->prepare($this->situationExam->id_examen);
        $this->actingAs(User::query()->findOrFail($this->docenteId));

        return $assistant;
    }

    public function testRoomChangeIsReflectedForBothRoomControllersWithoutCountingTwice(): void
    {
        $assistant = $this->openEntryControlWithDestinationAssistant();
        $before = $this->getJson($this->entryUrl('estado'))->assertOk()
            ->assertJsonPath('data.ultimos_ingresos.0.nro_aula', $this->roomNumber($this->aulaId))
            ->json('data');

        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();

        $after = $this->getJson($this->entryUrl('estado'))->assertOk()
            ->assertJsonPath('data.ultimos_ingresos.0.nro_aula', $this->roomNumber($this->otraAulaId))
            ->json('data');
        $this->assertNotSame($before['version'], $after['version']);
        $this->assertSame($before['ingresados'], $after['ingresados']);
        $this->assertSame($before['pendientes'], $after['pendientes']);
        $this->assertSame($before['total'], $after['total']);
        $this->assertCount(count($before['ultimos_ingresos']), $after['ultimos_ingresos']);

        $this->actingAs($assistant);
        $this->getJson($this->entryUrl('estado'))->assertOk()
            ->assertJsonPath('data.ultimos_ingresos.0.nro_aula', $this->roomNumber($this->otraAulaId));
        $this->postJson($this->entryUrl('verificar'), [
            'cod_sis' => $this->situationStudent->cod_sis,
            'ci' => $this->situationStudent->ci,
            'id_ambiente' => $this->otraAulaId,
        ])->assertOk()->assertJsonPath('data.veredicto', 'DUPLICADO')
            ->assertJsonPath('data.ingreso_previo.id_ambiente', $this->otraAulaId)
            ->assertJsonPath('data.ingreso_previo.nro_aula', $this->roomNumber($this->otraAulaId));
    }

    public function testIdentityObservationLeavesTheSnapshotUntouched(): void
    {
        $this->openEntryControlWithDestinationAssistant();
        $before = $this->getJson($this->entryUrl('estado'))->assertOk()->json('data');

        $this->postJson($this->situationUrl(), [
            'tipo' => 'IDENTIFICACION', 'motivo' => 'Documento deteriorado.',
        ])->assertCreated();

        $this->assertSame($before, $this->getJson($this->entryUrl('estado'))->assertOk()->json('data'));
    }

    public function testRoomChangeWithoutAnActiveSnapshotStillSucceeds(): void
    {
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();

        $this->assertSame($this->otraAulaId, (int) $this->attendance()->id_ambiente);
    }

    public function testRedisFailureLogsInvalidatesTheSnapshotAndKeepsTheRegistration(): void
    {
        $this->openEntryControlWithDestinationAssistant();
        $snapshots = $this->partialMock(EntryControlSnapshotService::class, function ($mock): void {
            $mock->shouldReceive('markRoomChanged')->once()->andThrow(new RuntimeException('Redis no disponible'));
        });
        $this->assertTrue($snapshots->ready($this->situationExam->id_examen));
        Log::spy();

        $this->postJson($this->situationUrl(), $this->situationPayload())->assertCreated();

        $this->assertSame($this->otraAulaId, (int) $this->attendance()->id_ambiente);
        $this->assertSame(1, DB::table('situacion_examen')->count());
        Log::shouldHaveReceived('error')->withArgs(
            fn ($message, $context = []) => str_contains($message, 'Cambio de ambiente persistido')
                && ($context['id_examen'] ?? null) === (int) $this->situationExam->id_examen
                && ($context['motivo'] ?? null) === 'Redis no disponible'
        )->once();
        $this->assertFalse($snapshots->ready($this->situationExam->id_examen));
        $this->getJson($this->entryUrl('estado'))->assertStatus(409);
    }

    private function attendance(): object
    {
        return DB::table('examen_estudiante')
            ->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)
            ->first();
    }
}
