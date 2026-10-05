<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class EntryControlStatusTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    public function test_estado_del_examen_devuelve_nomina_y_latido(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam([], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        app(RoomAssignmentService::class)->prepare($exam->id_examen);

        $this->actingAs(User::query()->findOrFail($this->docenteId))
            ->getJson("/api/control-ingreso/examenes/{$exam->id_examen}/estado")
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.ingresados', 0)
            ->assertJsonPath('data.pendientes', 2)
            ->assertJsonStructure(['data' => ['conectados', 'version', 'ultimos_ingresos']]);
    }

    public function test_el_creador_ve_su_examen_programado_sin_poder_verificar_aun(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam([], [$this->aulaId]);

        $this->actingAs(User::query()->findOrFail($this->docenteId))
            ->getJson('/api/control-ingreso/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.0.estado', Exam::PROGRAMADO);

        $this->getJson("/api/control-ingreso/examenes/{$exam->id_examen}/contexto")
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.estado', Exam::PROGRAMADO);

        $this->postJson("/api/control-ingreso/examenes/{$exam->id_examen}/verificar", [
            'cod_sis' => '202300001',
            'ci' => '1234567',
            'id_ambiente' => $this->aulaId,
        ])->assertStatus(409);

        $this->actingAs(User::query()->findOrFail($this->otroDocenteId))
            ->getJson("/api/control-ingreso/examenes/{$exam->id_examen}/contexto")
            ->assertForbidden();
    }

    public function test_examen_cuya_duracion_termino_no_aparece_ni_admite_contexto(): void
    {
        $timezone = config('sciem.zona_horaria');
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', $timezone));
        try {
            $this->seedExamCatalog();
            $exam = $this->createExam([
                'fecha' => '2026-10-01',
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
                'duracion' => 60,
                'estado' => Exam::EN_INGRESO,
            ], [$this->aulaId]);

            $this->actingAs(User::query()->findOrFail($this->docenteId))
                ->getJson('/api/control-ingreso/examenes')
                ->assertOk()
                ->assertJsonMissing(['id_examen' => $exam->id_examen]);

            $this->getJson("/api/control-ingreso/examenes/{$exam->id_examen}/contexto")
                ->assertStatus(409)
                ->assertJsonPath('message', 'El tiempo de control de ingreso del examen ya terminó.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_lista_contexto_y_aula_fija_del_auxiliar(): void
    {
        $this->seedExamCatalog();
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO], [$this->aulaId, $this->otraAulaId]);
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->aulaId,
        ]);

        $this->actingAs(User::query()->findOrFail($this->otroDocenteId))
            ->getJson('/api/control-ingreso/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id_examen', $exam->id_examen);

        $this->getJson("/api/control-ingreso/examenes/{$exam->id_examen}/contexto")
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'AUXILIAR')
            ->assertJsonPath('data.id_ambiente_asignado', $this->aulaId);

        $this->actingAs(User::query()->findOrFail($this->docenteId))
            ->getJson("/api/control-ingreso/examenes/{$exam->id_examen}/contexto")
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.id_ambiente_asignado', null)
            ->assertJsonCount(2, 'data.ambientes');
    }
}
