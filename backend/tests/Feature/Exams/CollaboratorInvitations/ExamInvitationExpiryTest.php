<?php

namespace Tests\Feature\Exams\CollaboratorInvitations;

use App\Exceptions\EntryControl\EntryOpeningException;
use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\Exams\ExamInvitationExpiryService;
use App\Services\Exams\ExamLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-026, criterio 10: cuando el examen sale de PROGRAMADO sus invitaciones pendientes quedan SIN_EFECTO.
 * Las escribe el ciclo de vida (abrir el ingreso, cancelar, finalizar) y las aceptadas o rechazadas no se tocan.
 */
class ExamInvitationExpiryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private Exam $exam;

    private ExamInvitation $pending;

    private ExamInvitation $accepted;

    private ExamInvitation $rejected;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        $this->exam = $this->createExam(['estado' => Exam::PROGRAMADO], [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $this->exam->id_examen, 'id_grupo' => $this->grupoPropioId]);

        $this->pending = $this->createInvitation($this->exam, $this->otroDocenteId);
        $this->accepted = $this->createInvitation(
            $this->exam,
            $this->createTeacherAccount('Ana', 'Aceptada', '200600001', $this->calculoId),
            ExamInvitation::ACEPTADA
        );
        $this->rejected = $this->createInvitation(
            $this->exam,
            $this->createTeacherAccount('Raúl', 'Rechazado', '200600002', $this->calculoId),
            ExamInvitation::RECHAZADA
        );
    }

    private function assertPendingVoided(): void
    {
        $pending = $this->pending->fresh();
        $this->assertSame(ExamInvitation::SIN_EFECTO, $pending->estado);
        $this->assertNotNull($pending->fecha_sin_efecto);
        $this->assertNull($pending->fecha_respuesta);

        $this->assertSame(ExamInvitation::ACEPTADA, $this->accepted->fresh()->estado);
        $this->assertNull($this->accepted->fresh()->fecha_sin_efecto);
        $this->assertSame(ExamInvitation::RECHAZADA, $this->rejected->fresh()->estado);
    }

    public function test_abrir_el_control_de_ingreso_deja_sin_efecto_las_pendientes(): void
    {
        app(RoomAssignmentService::class)->prepare((int) $this->exam->id_examen);

        $this->assertSame(Exam::PROGRAMADO, $this->exam->fresh()->estado);
        $this->assertNotNull($this->exam->fresh()->ingreso_abierto_en);
        $this->assertPendingVoided();
    }

    public function test_una_apertura_fallida_no_toca_las_invitaciones(): void
    {
        DB::table('examen_ambiente')->where('id_examen', $this->exam->id_examen)->delete();

        try {
            app(RoomAssignmentService::class)->prepare((int) $this->exam->id_examen);
            $this->fail('La apertura debía fallar por falta de ambientes.');
        } catch (EntryOpeningException $exception) {
            // Esperado: la transacción se revierte completa.
        }

        $this->assertSame(Exam::PROGRAMADO, $this->exam->fresh()->estado);
        $this->assertSame(ExamInvitation::PENDIENTE, $this->pending->fresh()->estado);
        $this->assertNull($this->pending->fresh()->fecha_sin_efecto);
    }

    public function test_cancelar_el_examen_deja_sin_efecto_las_pendientes(): void
    {
        $this->postJson("/api/examenes/{$this->exam->id_examen}/cancelar")->assertOk();

        $this->assertPendingVoided();
    }

    public function test_finalizar_automaticamente_desde_programado_deja_sin_efecto_las_pendientes(): void
    {
        $timezone = config('sciem.zona_horaria');
        $exam = $this->createExam([
            'fecha' => '2026-10-01',
            'hora_inicio' => '10:04',
            'duracion' => 90,
            'estado' => Exam::PROGRAMADO,
        ]);
        $invitation = $this->createInvitation($exam, $this->otroDocenteId);

        // Un examen que quedó sin iniciar conserva 120 minutos de recuperación desde su fin (11:34).
        $deadline = Carbon::parse('2026-10-01 13:34:00', $timezone);
        $this->assertSame(1, app(ExamLifecycleService::class)->finishExpired($deadline->copy()->addMinute()));

        $this->assertSame(Exam::FINALIZADO, $exam->fresh()->estado);
        $this->assertSame(ExamInvitation::SIN_EFECTO, $invitation->fresh()->estado);
        $this->assertNotNull($invitation->fresh()->fecha_sin_efecto);
    }

    public function test_no_toca_las_invitaciones_de_otros_examenes(): void
    {
        $other = $this->createExam(['nombre_examen' => 'Otro']);
        $otherInvitation = $this->createInvitation($other, $this->otroDocenteId);

        app(ExamInvitationExpiryService::class)->voidPendingForExam((int) $this->exam->id_examen);

        $this->assertSame(ExamInvitation::PENDIENTE, $otherInvitation->fresh()->estado);
    }

    public function test_repetirlo_no_cambia_nada(): void
    {
        $service = app(ExamInvitationExpiryService::class);

        $this->assertSame(1, $service->voidPendingForExam((int) $this->exam->id_examen));
        $firstDate = $this->pending->fresh()->fecha_sin_efecto;

        $this->assertSame(0, $service->voidPendingForExam((int) $this->exam->id_examen, Carbon::now()->addDay()));
        $this->assertTrue($firstDate->equalTo($this->pending->fresh()->fecha_sin_efecto));
    }

    public function test_tras_quedar_sin_efecto_se_puede_invitar_de_nuevo_a_otro_examen_programado(): void
    {
        app(ExamInvitationExpiryService::class)->voidPendingForExam((int) $this->exam->id_examen);
        $next = $this->createExam(['nombre_examen' => 'Siguiente']);

        $this->postJson($this->invitationsUrl($next), ['id_docente' => $this->otroDocenteId])->assertCreated();
    }
}
