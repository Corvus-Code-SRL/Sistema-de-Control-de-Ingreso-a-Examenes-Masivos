<?php

namespace Tests\Feature\EntryControl;

use App\Exceptions\EntryControl\EntryOpeningException;
use App\Models\Action;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** La apertura del control de ingreso deja una auditoría que alimenta el historial (HU-052). */
class RoomAssignmentAuditTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_prepare_audita_una_sola_vez_la_apertura(): void
    {
        $exam = $this->createExam(['estado' => Exam::PROGRAMADO], [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);

        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        $logs = AuditLog::query()->where('tabla_afectada', 'examen')->get();
        $this->assertCount(1, $logs);
        $this->assertSame('MODIFICAR', Action::query()->whereKey($logs[0]->id_accion)->value('operacion'));
        $this->assertSame('APERTURA_CONTROL', $logs[0]->nuevo_valor['evento']);
        $this->assertSame($this->docenteId, $logs[0]->id_usuario);
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);

        // Reintento con el examen ya EN_INGRESO: no duplica la auditoría.
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $this->assertSame(1, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }

    public function test_una_apertura_fallida_no_deja_auditoria_ni_cambia_el_estado(): void
    {
        $exam = $this->createExam(['estado' => Exam::PROGRAMADO], []);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);

        $failed = false;

        try {
            app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        } catch (EntryOpeningException $exception) {
            $failed = true;
        }

        $this->assertTrue($failed, 'la apertura debía fallar por falta de ambientes');
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
        $this->assertSame(0, AuditLog::query()->where('tabla_afectada', 'examen')->count());
    }
}
