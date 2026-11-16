<?php

namespace Tests\Feature\Exams\CollaboratorInvitations;

use App\Models\Exam;
use App\Models\ExamInvitation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/** HU-026, criterios 7 y 9: la lista de invitaciones enviadas y sus estados. */
class SentInvitationsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private const URL = '/api/docente/invitaciones';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_sin_invitaciones_devuelve_una_lista_vacia(): void
    {
        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_cada_invitacion_trae_docente_examen_fecha_de_envio_y_estado(): void
    {
        $exam = $this->createExam(['nombre_examen' => 'Parcial', 'fecha' => '2030-03-05', 'hora_inicio' => '09:00']);
        $this->createInvitation($exam, $this->otroDocenteId, ExamInvitation::PENDIENTE, [
            'fecha_invitacion' => '2026-11-10 12:00:00+00',
            'permite_agregar_grupos' => false,
        ]);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'PENDIENTE')
            ->assertJsonPath('data.0.fecha_invitacion', '2026-11-10T12:00:00+00:00')
            ->assertJsonPath('data.0.permite_agregar_grupos', false)
            ->assertJsonPath('data.0.docente.nombre_completo', 'Luis Vargas')
            ->assertJsonPath('data.0.docente.cod_sis', '200100002')
            ->assertJsonPath('data.0.examen.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.examen.nombre_examen', 'Parcial')
            ->assertJsonPath('data.0.examen.materia', 'Calculo II')
            ->assertJsonPath('data.0.examen.fecha', '2030-03-05')
            ->assertJsonPath('data.0.examen.hora_inicio', '09:00');
    }

    public function test_muestra_los_cuatro_estados_de_la_invitacion(): void
    {
        $exam = $this->createExam();
        $states = [
            ExamInvitation::PENDIENTE,
            ExamInvitation::ACEPTADA,
            ExamInvitation::RECHAZADA,
            ExamInvitation::SIN_EFECTO,
        ];

        foreach ($states as $i => $state) {
            $teacher = $this->createTeacherAccount("Docente{$i}", 'Estado', sprintf('20040000%d', $i), $this->calculoId);
            $this->createInvitation($exam, $teacher, $state, [
                'fecha_invitacion' => '2026-11-0' . ($i + 1) . ' 10:00:00+00',
            ]);
        }

        $response = $this->getJson(self::URL)->assertOk();

        $this->assertSame(
            array_reverse($states),
            $response->json('data.*.estado')
        );
        $this->assertNotNull($response->json('data.0.fecha_sin_efecto'));
        $this->assertNotNull($response->json('data.1.fecha_respuesta'));
        $this->assertNotNull($response->json('data.2.fecha_respuesta'));
    }

    public function test_las_ordena_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $exam = $this->createExam();
        $older = $this->createTeacherAccount('Antiguo', 'Orden', '200400101', $this->calculoId);
        $newer = $this->createTeacherAccount('Nuevo', 'Orden', '200400102', $this->calculoId);

        $this->createInvitation($exam, $older, ExamInvitation::PENDIENTE, [
            'fecha_invitacion' => '2026-11-01 10:00:00+00',
        ]);
        $this->createInvitation($exam, $newer, ExamInvitation::PENDIENTE, [
            'fecha_invitacion' => '2026-11-05 10:00:00+00',
        ]);

        $this->assertSame(
            [$newer, $older],
            $this->getJson(self::URL)->assertOk()->json('data.*.docente.id_usuario')
        );
    }

    public function test_solo_lista_las_invitaciones_que_envio_el_docente(): void
    {
        $other = $this->createExam(['id_usuario_docente' => $this->otroDocenteId, 'nombre_examen' => 'Ajeno']);
        $mine = $this->createExam(['nombre_examen' => 'Propio']);
        $third = $this->createTeacherAccount('Tercero', 'Ajeno', '200400201', $this->calculoId);

        $this->createInvitation($other, $third);
        $this->createInvitation($mine, $this->otroDocenteId);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.examen.nombre_examen', 'Propio');
    }

    public function test_una_pendiente_de_un_examen_que_ya_no_esta_programado_se_muestra_sin_efecto(): void
    {
        $exam = $this->createExam($this->examStateAttributes('INGRESO_ABIERTO'));
        $this->createInvitation($exam, $this->otroDocenteId);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.0.estado', 'SIN_EFECTO')
            ->assertJsonPath('data.0.fecha_sin_efecto', null);

        // El estado guardado no cambia: la derivación es solo de lectura.
        $this->assertSame(ExamInvitation::PENDIENTE, ExamInvitation::query()->sole()->estado);
    }

    public function test_una_aceptada_se_conserva_aunque_el_examen_ya_no_este_programado(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO]);
        $this->createInvitation($exam, $this->otroDocenteId, ExamInvitation::ACEPTADA);

        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.estado', 'ACEPTADA');
    }

    public function test_no_hace_una_consulta_por_invitacion(): void
    {
        $exam = $this->createExam();
        foreach (range(1, 6) as $i) {
            $teacher = $this->createTeacherAccount(
                "Docente{$i}",
                'Consultas',
                sprintf('20050000%d', $i),
                $this->calculoId
            );
            $this->createInvitation($exam, $teacher);
        }

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->getJson(self::URL)->assertOk()->assertJsonCount(6, 'data');

        $this->assertLessThan(20, $count, "Demasiadas consultas para 6 invitaciones: {$count}");
    }
}
