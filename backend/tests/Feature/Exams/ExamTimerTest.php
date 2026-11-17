<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\AuditLog;
use Database\Seeders\ActionSeeder;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamTimerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->travelTo(Carbon::parse('2026-11-17T14:20:00-04:00'));
        $this->actAsTeacher($this->docenteId);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_inicio_persiste_el_mismo_cronometro_y_respeta_el_fin_programado(): void
    {
        $exam = $this->inProgressExam(['ingreso_abierto_en' => now()->subMinutes(30)]);
        $started = $this->postJson($this->url($exam), ['minutos' => 100])->assertOk()
            ->assertJsonPath('data.examen.estado', Exam::EN_CURSO)
            ->assertJsonPath('data.examen.cronometro_iniciado_en', '2026-11-17T18:20:00+00:00')
            ->assertJsonPath('data.examen.cronometro_vence_en', '2026-11-17T20:00:00+00:00')
            ->json('data.examen');

        $this->travel(3)->minutes();
        $this->postJson($this->url($exam), ['minutos' => 1])->assertStatus(409);
        $this->postJson($this->url($exam), ['minutos' => 100])->assertOk()->assertJsonPath('data.examen', $started);
        $this->getJson('/api/examenes/cronometros')->assertOk()
            ->assertJsonPath('data.examenes.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.examenes.0.cronometro_iniciado_en', $started['cronometro_iniciado_en'])
            ->assertJsonPath('data.examenes.0.cronometro_vence_en', $started['cronometro_vence_en']);
        $this->getJson($this->url($exam))->assertOk()->assertJsonPath('data.examen', $started);

        $fresh = $exam->fresh();
        $this->assertSame(Exam::EN_CURSO, $fresh->estado);
        $this->assertNull($fresh->inicio_real_en);
        $this->assertNull($fresh->fin_real_en);
        $this->assertNotNull($fresh->ingreso_abierto_en);
        $this->assertNull($fresh->ingreso_ordinario_cerrado_en);
    }

    public function test_los_minutos_son_independientes_y_el_ajuste_conserva_el_inicio(): void
    {
        $first = $this->inProgressExam();
        $second = $this->inProgressExam(['nombre_examen' => 'Segundo parcial']);
        $this->postJson($this->url($first), ['minutos' => 20])->assertOk()
            ->assertJsonPath('data.examen.cronometro_vence_en', '2026-11-17T18:40:00+00:00');
        $this->postJson($this->url($second), ['minutos' => 5])->assertOk();

        $this->travel(6)->minutes();
        $this->getJson('/api/examenes/cronometros')->assertOk()->assertJsonCount(2, 'data.examenes')
            ->assertJsonPath('data.examenes.1.estado', Exam::EN_CURSO);
        $this->putJson($this->url($second), ['minutos' => 10])->assertOk()
            ->assertJsonPath('data.examen.cronometro_iniciado_en', '2026-11-17T18:20:00+00:00')
            ->assertJsonPath('data.examen.cronometro_vence_en', '2026-11-17T18:30:00+00:00');
        $this->putJson($this->url($second), ['minutos' => 100])->assertOk()
            ->assertJsonPath('data.examen.cronometro_vence_en', '2026-11-17T20:00:00+00:00');
        $this->assertSame('2026-11-17T18:40:00+00:00', $first->fresh()->cronometro_vence_en->utc()->toIso8601String());
        $this->assertNull($second->fresh()->fin_real_en);
    }

    public function test_no_se_puede_acceder_al_cronometro_de_otro_docente(): void
    {
        $exam = $this->inProgressExam();
        $this->postJson($this->url($exam), ['minutos' => 20])->assertOk();

        $this->actAsTeacher($this->otroDocenteId);
        $this->getJson('/api/examenes/cronometros')->assertOk()->assertJsonCount(0, 'data.examenes');
        $this->getJson($this->url($exam))->assertForbidden()
            ->assertJsonPath('message', 'Solo el docente responsable del examen puede controlar su tiempo');
        $this->postJson($this->url($exam), ['minutos' => 20])->assertForbidden();
        $this->putJson($this->url($exam), ['minutos' => 10])->assertForbidden();
        $this->actAsGuest();
        $this->getJson('/api/examenes/cronometros')->assertUnauthorized();
    }

    public function test_rechaza_minutos_invalidos_y_examenes_fuera_de_plazo(): void
    {
        $exam = $this->inProgressExam();
        $this->postJson($this->url($exam), ['minutos' => 0])->assertUnprocessable();
        $this->postJson($this->url($exam), ['minutos' => 1.5])->assertUnprocessable();
        $this->putJson($this->url($exam), ['minutos' => 10])->assertStatus(409);
        $this->travelTo(Carbon::parse('2026-11-17T16:00:00-04:00'));
        $this->postJson($this->url($exam), ['minutos' => 1])->assertUnprocessable();
        $this->assertNull($exam->fresh()->cronometro_iniciado_en);
    }

    public function test_solo_un_examen_en_curso_permite_iniciar_o_ajustar(): void
    {
        foreach ([Exam::PROGRAMADO, Exam::FINALIZADO, Exam::CANCELADO] as $state) {
            $exam = $this->inProgressExam(['estado' => $state]);
            $message = 'Solo puede controlar el tiempo de un examen en curso.';
            $this->postJson($this->url($exam), ['minutos' => 2])->assertStatus(409)
                ->assertJsonPath('message', $message);
            $this->putJson($this->url($exam), ['minutos' => 2])->assertStatus(409)
                ->assertJsonPath('message', $message);
            $this->assertNull($exam->fresh()->cronometro_iniciado_en);
        }
    }

    public function test_el_listado_solo_incluye_examenes_propios_en_curso_aunque_no_tengan_cronometro(): void
    {
        $exam = $this->inProgressExam();
        $this->inProgressExam(['estado' => Exam::PROGRAMADO]);
        $this->inProgressExam(['estado' => Exam::FINALIZADO]);
        $this->inProgressExam(['estado' => Exam::CANCELADO]);
        $this->inProgressExam(['id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson('/api/examenes/cronometros')->assertOk()->assertJsonCount(1, 'data.examenes')
            ->assertJsonPath('data.examenes.0.id_examen', $exam->id_examen)
            ->assertJsonPath('data.examenes.0.cronometro_iniciado_en', null);

        $this->postJson("/api/examenes/{$exam->id_examen}/finalizar")->assertOk();
        $this->getJson('/api/examenes/cronometros')->assertOk()->assertJsonCount(0, 'data.examenes');
    }

    public function test_un_auxiliar_no_puede_consultar_ni_modificar_cronometros(): void
    {
        $exam = $this->inProgressExam();
        $role = Role::firstOrCreate(
            ['nombre_rol' => Role::AUXILIAR],
            ['descripcion' => 'Auxiliar de docencia', 'estado' => 'ACTIVO']
        );
        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->update(['id_rol' => $role->id_rol]);

        $this->getJson('/api/examenes/cronometros')->assertForbidden();
        $this->getJson($this->url($exam))->assertForbidden();
        $this->postJson($this->url($exam), ['minutos' => 20])->assertForbidden();
        $this->putJson($this->url($exam), ['minutos' => 10])->assertForbidden();
        $this->assertNull($exam->fresh()->cronometro_iniciado_en);
    }

    public function test_el_cierre_externo_retira_la_cuenta_sin_borrar_sus_marcas(): void
    {
        $exam = $this->inProgressExam();
        $this->postJson($this->url($exam), ['minutos' => 20])->assertOk();
        $this->postJson("/api/examenes/{$exam->id_examen}/finalizar")->assertOk();

        $this->getJson('/api/examenes/cronometros')->assertOk()->assertJsonCount(0, 'data.examenes');
        $this->putJson($this->url($exam), ['minutos' => 10])->assertStatus(409);
        $this->assertNotNull($exam->fresh()->cronometro_iniciado_en);
        $this->assertNotNull($exam->fresh()->fin_real_en);
    }

    public function test_rechaza_excesos_sin_cambiar_fechas_y_acepta_el_limite_exacto(): void
    {
        $exam = $this->inProgressExam();
        $message = 'El tiempo solicitado excede el límite disponible';
        $this->postJson($this->url($exam), ['minutos' => 101])->assertUnprocessable()
            ->assertJsonValidationErrors('minutos')->assertJsonPath('errors.minutos.0', $message)
            ->assertJsonPath('message', $message);
        $this->assertNull($exam->fresh()->cronometro_iniciado_en);
        $this->postJson($this->url($exam), ['minutos' => 100])->assertOk();
        $this->travel(2)->minutes();
        $this->putJson($this->url($exam), ['minutos' => 101])->assertUnprocessable()
            ->assertJsonPath('errors.minutos.0', $message);
        $this->putJson($this->url($exam), ['minutos' => 100])->assertOk()
            ->assertJsonPath('data.examen.cronometro_vence_en', '2026-11-17T20:00:00+00:00');
        $this->travelTo(Carbon::parse('2026-11-17T20:00:00Z'));
        $this->putJson($this->url($exam), ['minutos' => 100])->assertUnprocessable();
    }

    public function test_el_limite_original_cruza_medianoche_sin_depender_de_hora_fin(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18T00:00:00-04:00'));
        $exam = $this->inProgressExam(['hora_inicio' => '23:00', 'hora_fin' => null]);
        $this->postJson($this->url($exam), ['minutos' => 61])->assertUnprocessable();
        $this->postJson($this->url($exam), ['minutos' => 60])->assertOk()
            ->assertJsonPath('data.examen.cronometro_vence_en', '2026-11-18T05:00:00+00:00');
    }

    public function test_un_colaborador_no_puede_operar_el_cronometro_ajeno(): void
    {
        $exam = $this->inProgressExam();
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoAjenoId]);
        $this->actAsTeacher($this->otroDocenteId);
        $this->getJson('/api/examenes/cronometros')->assertOk()->assertJsonCount(0, 'data.examenes');
        $this->getJson($this->url($exam))->assertForbidden();
        $this->postJson($this->url($exam), ['minutos' => 10])->assertForbidden();
        $this->putJson($this->url($exam), ['minutos' => 10])->assertForbidden();
    }

    public function test_el_invitado_no_tiene_la_capacidad_del_cronometro_aunque_sea_propietario(): void
    {
        $exam = $this->inProgressExam();
        $role = Role::firstOrCreate(['nombre_rol' => Role::INVITADO], ['estado' => 'ACTIVO']);
        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->update(['id_rol' => $role->id_rol]);
        $this->getJson('/api/examenes/cronometros')->assertForbidden();
        $this->getJson($this->url($exam))->assertForbidden();
        $this->postJson($this->url($exam), ['minutos' => 10])->assertForbidden();
        $this->putJson($this->url($exam), ['minutos' => 10])->assertForbidden();
    }

    public function test_audita_inicio_y_ajuste_sin_duplicar_reintentos_ni_rechazos(): void
    {
        $this->seed(ActionSeeder::class);
        $this->assertDatabaseHas('accion', ['id_accion' => 28, 'operacion' => 'INICIAR_CRONOMETRO']);
        $this->assertDatabaseHas('accion', ['id_accion' => 29, 'operacion' => 'AJUSTAR_CRONOMETRO']);
        $exam = $this->inProgressExam();
        $this->postJson($this->url($exam), ['minutos' => 10])->assertOk();
        $this->postJson($this->url($exam), ['minutos' => 10])->assertOk();
        $this->postJson($this->url($exam), ['minutos' => 20])->assertStatus(409);
        $this->putJson($this->url($exam), ['minutos' => 101])->assertUnprocessable();
        $this->travel(11)->minutes();
        $this->putJson($this->url($exam), ['minutos' => 20])->assertOk();
        $logs = AuditLog::whereIn('id_accion', [28, 29])->orderBy('id_log')->get();
        $this->assertCount(2, $logs);
        $this->assertSame([28, 29], $logs->pluck('id_accion')->all());
        $this->assertSame($this->docenteId, $logs[0]->id_usuario);
        $this->assertSame('examen', $logs[0]->tabla_afectada);
        $this->assertNull($logs[0]->antiguo_valor['cronometro_iniciado_en']);
        $this->assertSame($exam->id_examen, $logs[1]->nuevo_valor['id_examen']);
        $this->assertSame($logs[0]->nuevo_valor, $logs[1]->antiguo_valor);
        $this->assertNotSame($logs[1]->antiguo_valor['cronometro_vence_en'], $logs[1]->nuevo_valor['cronometro_vence_en']);
    }

    private function inProgressExam(array $overrides = []): Exam
    {
        return $this->createExam(array_merge([
            'fecha' => '2026-11-17',
            'hora_inicio' => '14:00',
            'duracion' => 120,
            'estado' => Exam::EN_CURSO,
        ], $overrides));
    }

    private function url(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}/cronometro";
    }
}
