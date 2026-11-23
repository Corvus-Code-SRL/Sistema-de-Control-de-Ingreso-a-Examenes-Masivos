<?php

namespace Tests\Feature\Exams\RoomDistribution;

use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\Exams\Distribution\RoomDistributionReader;
use App\Services\Exams\Distribution\RoomDistributionStopService;
use App\Support\Exams\DistributionMessages;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

/** HU-031 CA-9: con el control abierto el creador puede dejar de usar la distribución. */
class RoomDistributionStopTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($this->exam)->assertOk();
    }

    public function test_antes_de_abrir_no_se_puede_dejar_de_usar(): void
    {
        $this->getJson($this->distributionUrl($this->exam))->assertOk()
            ->assertJsonPath('data.examen.puede_dejar_de_usar', false);
        $this->stop()->assertStatus(409)->assertJsonPath('message', DistributionMessages::CANNOT_STOP);
    }

    public function test_el_creador_la_deja_de_usar_con_bitacora_y_es_irreversible(): void
    {
        app(RoomAssignmentService::class)->prepare((int) $this->exam->id_examen);
        $this->getJson($this->distributionUrl($this->exam))->assertOk()
            ->assertJsonPath('data.examen.puede_dejar_de_usar', true);

        $this->stop()->assertOk()
            ->assertJsonPath('mensaje', DistributionMessages::STOPPED)
            ->assertJsonPath('data.examen.distribucion_suspendida', true)
            ->assertJsonPath('data.examen.puede_dejar_de_usar', false)
            ->assertJsonPath('data.motivo_solo_lectura', DistributionMessages::SUSPENDED);

        $this->assertSame($this->docenteId, (string) $this->exam->fresh()->id_usuario_suspende_distribucion);
        $this->assertSame([], app(RoomDistributionReader::class)->assignedRooms((int) $this->exam->id_examen));
        $this->assertSame(1, DB::table('log')->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('accion.operacion', RoomDistributionStopService::AUDIT_ACTION)->count());
        $this->stop()->assertStatus(409);
    }

    public function test_un_colaborador_no_puede_dejar_de_usarla(): void
    {
        app(RoomAssignmentService::class)->prepare((int) $this->exam->id_examen);
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->stop()->assertForbidden();
        $this->assertNull($this->exam->fresh()->distribucion_suspendida_en);
    }

    public function test_selector_lista_en_curso_solo_con_distribucion(): void
    {
        $withoutPlan = $this->distributionExam(2, [3, 3]);
        Exam::query()->whereKey([$this->exam->id_examen, $withoutPlan->id_examen])
            ->update(['estado' => Exam::EN_CURSO, 'ingreso_abierto_en' => now()]);

        $options = collect($this->getJson('/api/examenes/distribucion')->assertOk()->json('data'));

        $option = $options->firstWhere('id_examen', $this->exam->id_examen);
        $this->assertSame(Exam::EN_CURSO, $option['estado']);
        $this->assertFalse($option['editable']);
        $this->assertTrue($option['puede_dejar_de_usar']);
        $this->assertNull($options->firstWhere('id_examen', $withoutPlan->id_examen));
    }

    private function stop(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson($this->distributionUrl($this->exam) . '/dejar-de-usar');
    }
}
