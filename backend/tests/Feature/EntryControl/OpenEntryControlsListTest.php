<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRoomDistribution;
use Tests\TestCase;

/** Listas de exámenes del docente: abiertos, por abrir y en curso. */
class OpenEntryControlsListTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRoomDistribution;

    private const LIST_URL = '/api/control-ingreso/examenes';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_examen_con_distribucion_guardada_aparece_tras_abrir(): void
    {
        $exam = $this->distributionExam(4, [3, 3]);
        $this->saveDistribution($exam)->assertOk();
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $this->getJson(self::LIST_URL)->assertOk()
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.control_abierto', true);
    }

    public function test_examen_por_abrir_aparece_para_el_docente(): void
    {
        $exam = $this->distributionExam(2, [3, 3]);

        $this->getJson(self::LIST_URL)->assertOk()
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.control_abierto', false);
    }

    public function test_un_examen_anterior_con_ingreso_cerrado_no_convierte_la_lista_en_objeto(): void
    {
        $closed = $this->distributionExam(2, [3, 3]);
        $open = $this->distributionExam(2, [3, 3]);
        DB::table('examen')->where('id_examen', $closed->id_examen)->update([
            'estado' => Exam::EN_CURSO,
            'ingreso_abierto_en' => now(),
            'ingreso_ordinario_cerrado_en' => now(),
        ]);
        DB::table('examen')->where('id_examen', $open->id_examen)
            ->update(['fecha' => $open->fecha->copy()->addDay()->toDateString()]);

        $response = $this->getJson(self::LIST_URL)->assertOk();

        $this->assertStringStartsWith('{"data":[', $response->getContent());
        $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_examen', $open->id_examen);
    }

    public function test_examen_en_curso_aparece_en_la_lista_de_examenes(): void
    {
        $exam = $this->distributionExam(2, [3, 3]);
        DB::table('examen')->where('id_examen', $exam->id_examen)
            ->update(['estado' => Exam::EN_CURSO, 'ingreso_abierto_en' => now()]);

        $listed = collect($this->getJson('/api/examenes')->assertOk()->json('data'))
            ->firstWhere('id_examen', $exam->id_examen);

        $this->assertSame(Exam::EN_CURSO, $listed['estado']);
    }
}
