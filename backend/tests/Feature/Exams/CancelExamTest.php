<?php

namespace Tests\Feature\Exams;

use App\Models\AuditLog;
use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-24 — criterio 12: un examen se cancela solo mientras está PROGRAMADO, y la
 * cancelación queda en la bitácora.
 */
class CancelExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    private function url(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}/cancelar";
    }

    private function cancellationLogs(): int
    {
        return AuditLog::where('tabla_afectada', 'examen')->count();
    }

    public function test_criterio_12_cancela_un_examen_programado_y_lo_registra_en_la_bitacora(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);

        $this->postJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('mensaje', 'Examen cancelado correctamente.')
            ->assertJsonPath('data.estado', Exam::CANCELADO);

        // No se borra: el examen queda registrado como cancelado.
        $this->assertDatabaseHas('examen', ['id_examen' => $exam->id_examen, 'estado' => Exam::CANCELADO]);
        $this->assertDatabaseHas('examen_ambiente', ['id_examen' => $exam->id_examen, 'id_ambiente' => $this->aulaId]);

        $log = AuditLog::where('tabla_afectada', 'examen')->sole();

        $this->assertNotNull($log->id_accion);
        $this->assertSame(
            (int) DB::table('accion')->where('operacion', 'CANCELAR')->value('id_accion'),
            (int) $log->id_accion
        );
        $this->assertSame($this->docenteId, $log->id_usuario);
        $this->assertNotNull($log->fecha_hora);
        // jsonb no conserva el orden de las claves.
        $this->assertEquals(['id_examen' => $exam->id_examen, 'estado' => Exam::PROGRAMADO], $log->antiguo_valor);
        $this->assertEquals(['id_examen' => $exam->id_examen, 'estado' => Exam::CANCELADO], $log->nuevo_valor);
    }

    public function test_criterio_12_desde_en_ingreso_ya_no_se_puede_cancelar(): void
    {
        foreach ([Exam::EN_INGRESO, Exam::EN_CURSO, Exam::FINALIZADO] as $status) {
            $exam = $this->createExam(['nombre_examen' => "Examen {$status}", 'estado' => $status]);

            $this->postJson($this->url($exam))
                ->assertStatus(409)
                ->assertJsonPath(
                    'message',
                    'El control de ingreso del examen ya se inició: el examen no puede cancelarse.'
                );

            $this->assertDatabaseHas('examen', ['id_examen' => $exam->id_examen, 'estado' => $status]);
        }

        $this->assertSame(0, $this->cancellationLogs());
    }

    public function test_un_examen_cancelado_no_se_cancela_de_nuevo(): void
    {
        $exam = $this->createExam(['estado' => Exam::CANCELADO]);

        $this->postJson($this->url($exam))
            ->assertStatus(409)
            ->assertJsonPath('message', 'El examen ya está cancelado.');

        $this->assertSame(0, $this->cancellationLogs());
    }

    public function test_solo_el_docente_creador_puede_cancelar(): void
    {
        $exam = $this->createExam(['id_usuario_docente' => $this->otroDocenteId]);

        $this->postJson($this->url($exam))->assertForbidden();

        $this->assertDatabaseHas('examen', ['id_examen' => $exam->id_examen, 'estado' => Exam::PROGRAMADO]);
        $this->assertSame(0, $this->cancellationLogs());
    }

    public function test_responde_404_si_el_examen_no_existe(): void
    {
        $this->postJson('/api/examenes/999999/cancelar')->assertNotFound();
    }
}
