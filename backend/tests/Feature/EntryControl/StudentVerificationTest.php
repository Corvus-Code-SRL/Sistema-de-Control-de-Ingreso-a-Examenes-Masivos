<?php

namespace Tests\Feature\EntryControl;

use App\Jobs\OpenEntryControlJob;
use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class StudentVerificationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private Exam $exam;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        $this->exam = $this->createExam([], [$this->aulaId, $this->otraAulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->student = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->select('estudiante.*')
            ->firstOrFail();

        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
        $this->actingAs(User::query()->findOrFail($this->docenteId));
    }

    private function rebuildSnapshot(): void
    {
        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
    }

    private function url(string $action): string
    {
        return "/api/control-ingreso/examenes/{$this->exam->id_examen}/{$action}";
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'cod_sis' => $this->student->cod_sis,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
        ], $overrides);
    }

    public function test_verifica_sin_crear_ingreso_y_busca_por_nombre(): void
    {
        $this->getJson($this->url('buscar') . '?nombre=Estudiante')
            ->assertOk()
            ->assertJsonPath('data.0.id_estudiante', $this->student->id_estudiante);

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.antecedentes.tiene_antecedentes', false);

        $this->assertSame(0, DB::table('examen_estudiante')->count());
        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function test_no_verifica_si_la_duracion_del_examen_ya_termino(): void
    {
        $timezone = config('sciem.zona_horaria');
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', $timezone));
        try {
            $this->exam->update([
                'fecha' => '2026-10-01',
                'hora_inicio' => '11:00',
                'hora_fin' => '12:30',
                'duracion' => 90,
            ]);
            $this->rebuildSnapshot();

            Carbon::setTestNow(Carbon::parse('2026-10-01 12:30:00', $timezone));
            $this->postJson($this->url('verificar'), $this->input())
                ->assertStatus(409)
                ->assertJsonPath('message', 'El tiempo de control de ingreso del examen ya terminó.');

            $this->assertSame(0, DB::table('examen_estudiante')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_verificacion_autorizada_se_resuelve_sin_consultas_sql(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO');

        $this->assertSame([], $queries);
    }

    public function test_ci_diferente_no_bloquea_la_verificacion_por_sis(): void
    {
        $originalCi = $this->student->ci;

        $this->postJson($this->url('verificar'), $this->input(['ci' => '999999999']))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.ci', $originalCi);

        $this->assertSame(0, DB::table('intento_ingreso')->count());
        $this->assertSame($originalCi, $this->student->fresh()->ci);
    }

    public function test_solo_sis_permite_verificar_y_confirmar(): void
    {
        $this->postJson($this->url('verificar'), [
            'cod_sis' => $this->student->cod_sis,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.foto_url', null);

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated()->assertJsonPath('data.veredicto', 'INGRESO_REGISTRADO');

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'estado_ingreso' => 'INGRESO',
        ]);
    }

    public function test_estudiante_de_otro_grupo_no_puede_ingresar(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);
        $other = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoAjenoId)
            ->select('estudiante.*')
            ->firstOrFail();
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), [
            'cod_sis' => $other->cod_sis,
            'ci' => $other->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()->assertJsonPath('data.veredicto', 'NO_PERTENECE');
    }

    public function test_aula_incorrecta_se_distingue_del_duplicado(): void
    {
        $this->postJson($this->url('verificar'), $this->input(['id_ambiente' => $this->otraAulaId]))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AULA_INCORRECTA')
            ->assertJsonPath('data.ambiente_asignado.id_ambiente', $this->aulaId);
    }

    public function test_confirma_una_vez_y_actualiza_estado_incluso_en_el_mismo_segundo(): void
    {
        $before = $this->getJson($this->url('estado'))->assertOk()->json('data');
        $this->assertSame(2, $before['total']);
        $this->assertSame(0, $before['ingresados']);

        $input = [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
        ];
        $this->postJson($this->url('confirmar-ingreso'), $input)
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'INGRESO_REGISTRADO');
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->docenteId,
            'estado_ingreso' => 'INGRESO',
        ]);
        $this->assertNotNull(DB::table('examen_estudiante')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $this->student->id_estudiante)
            ->value('registrado_en'));

        $after = $this->getJson($this->url('estado') . '?desde=' . $before['version'])
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $after['ingresados']);
        $this->assertSame(1, $after['pendientes']);
        $this->assertNotSame($before['version'], $after['version']);
        $this->assertCount(1, $after['ultimos_ingresos']);

        $this->postJson($this->url('confirmar-ingreso'), $input)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', 'DUPLICADO');
        $this->assertSame(1, DB::table('examen_estudiante')->count());
    }

    public function test_dos_ingresos_en_el_mismo_segundo_generan_versiones_distintas(): void
    {
        $students = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->select('estudiante.*')
            ->get();

        $first = $students[0];
        $second = $students[1];
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $first->id_estudiante,
            'ci' => $first->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
        $version = $this->getJson($this->url('estado'))->json('data.version');

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $second->id_estudiante,
            'ci' => $second->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
        $after = $this->getJson($this->url('estado') . '?desde=' . $version)->json('data');
        $this->assertNotSame($version, $after['version']);
        $this->assertSame(2, $after['ingresados']);
        $this->assertSame(0, $after['pendientes']);
    }

    public function test_codigo_desconocido_no_revela_datos(): void
    {
        $this->postJson($this->url('verificar'), [
            'cod_sis' => '999999999',
            'ci' => '99999999',
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_ENCONTRADO')
            ->assertJsonPath('data.estudiante', null);
    }

    public function test_sis_desconocido_sin_ci_registra_intento(): void
    {
        $this->postJson($this->url('verificar'), [
            'cod_sis' => '999999999',
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_ENCONTRADO');

        $this->assertDatabaseHas('intento_ingreso', [
            'id_examen' => $this->exam->id_examen,
            'cod_sis' => '999999999',
            'ci_presentado' => null,
            'motivo' => 'NO_ENCONTRADO',
        ]);
    }

    public function test_no_habilitado_no_puede_confirmar(): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
            'hora_ingreso' => null,
        ]);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO');
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertUnprocessable()->assertJsonPath('veredicto', 'NO_HABILITADO');

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }

    public function test_examen_cerrado_no_se_puede_verificar(): void
    {
        $this->exam->update(['estado' => Exam::EN_CURSO]);
        app(\App\Services\EntryControl\EntryControlSnapshotService::class)
            ->deactivate($this->exam->id_examen);
        $this->postJson($this->url('verificar'), $this->input())->assertStatus(409);
        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function test_ci_pendiente_no_se_captura_al_confirmar(): void
    {
        $this->student->update(['ci' => null]);
        $this->rebuildSnapshot();
        $input = [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => '87654321',
            'id_ambiente' => $this->aulaId,
        ];

        $this->postJson($this->url('verificar'), [
            'cod_sis' => $this->student->cod_sis,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.ci', null)
            ->assertJsonPath('data.estudiante.ci_pendiente', true);

        $this->postJson($this->url('confirmar-ingreso'), $input)->assertCreated();
        $this->assertNull($this->student->fresh()->ci);
    }

    public function test_rechazo_sin_ci_guarda_el_intento_sin_ci_presentado(): void
    {
        $this->postJson($this->url('rechazar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'motivo' => 'IDENTIDAD_DUDOSA',
        ])->assertCreated();

        $this->assertDatabaseHas('intento_ingreso', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'ci_presentado' => null,
        ]);
    }

    public function test_rechazo_humano_no_registra_ingreso(): void
    {
        $this->postJson($this->url('rechazar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
            'motivo' => 'IDENTIDAD_DUDOSA',
            'observacion' => 'Foto no coincide.',
        ])->assertCreated()->assertJsonPath('data.registrado', true);

        $this->assertSame(1, DB::table('intento_ingreso')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_alerta_de_riesgo_no_bloquea_el_ingreso(): void
    {
        $reasonId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Suplantacion'], 'id_falta');
        $reportId = DB::table('reporte_estudiante')->insertGetId([
            'id_estudiante' => $this->student->id_estudiante,
            'id_examen' => $this->exam->id_examen,
            'id_tipo_falta' => $reasonId,
            'id_usuario_reportante' => $this->docenteId,
            'evidencia_url' => 'https://example.test/evidencia',
            'evidencia_public_id' => 'test',
            'estado' => 'APROBADO',
            'fecha_revision' => now(),
            'id_usuario_revisor' => $this->docenteId,
        ], 'id_reporte_est');
        DB::table('central_riesgo')->insert([
            'id_reporte_est' => $reportId,
            'id_estudiante' => $this->student->id_estudiante,
        ]);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.antecedentes.cantidad', 1)
            ->assertJsonPath('data.antecedentes.tiene_antecedentes', true);
    }

    public function test_auxiliar_solo_puede_controlar_su_ambiente(): void
    {
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->aulaId,
        ]);
        $this->rebuildSnapshot();
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO');
        $this->postJson($this->url('verificar'), $this->input(['id_ambiente' => $this->otraAulaId]))
            ->assertForbidden();
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->otraAulaId,
        ])->assertForbidden();
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->otroDocenteId,
        ]);
    }

    public function test_auxiliar_sin_ambiente_asignado_no_puede_controlar(): void
    {
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => null,
        ]);
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->postJson($this->url('verificar'), $this->input())->assertForbidden();
        $this->getJson($this->url('buscar') . '?nombre=Estudiante')->assertForbidden();
    }

    public function test_solicitud_mal_formada_no_crea_intento(): void
    {
        $this->postJson($this->url('verificar'), $this->input([
            'id_estudiante' => $this->student->id_estudiante,
        ]))->assertUnprocessable()->assertJsonValidationErrors('id_estudiante');

        $this->postJson($this->url('verificar'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertUnprocessable()->assertJsonValidationErrors('cod_sis');

        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function test_apertura_falla_sin_capacidad_y_conserva_programado(): void
    {
        $other = $this->createExam(['nombre_examen' => 'Sin capacidad'], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $other->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => 1]);

        try {
            app(RoomAssignmentService::class)->prepare($other->id_examen);
            $this->fail('La apertura debía fallar.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('capacidad', $exception->getMessage());
        }

        $this->assertSame(Exam::PROGRAMADO, $other->fresh()->estado);
        $this->assertFalse(Schema::hasTable('examen_estudiante_ambiente'));
    }

    public function test_apertura_automatica_respeta_treinta_minutos_configurados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00', config('sciem.zona_horaria')));
        try {
            $exam = $this->createExam([
                'fecha' => '2026-09-29',
                'hora_inicio' => '12:31',
                'hora_fin' => '14:01',
            ], [$this->aulaId]);
            DB::table('grupo_examen')->insert([
                'id_examen' => $exam->id_examen,
                'id_grupo' => $this->grupoPropioId,
            ]);
            $this->assertSame(10, $exam->fresh()->minutos_apertura);
            DB::table('examen')->where('id_examen', $exam->id_examen)
                ->update(['minutos_apertura' => 30]);

            $this->assertSame(30, $exam->fresh()->minutos_apertura);
            app(OpenEntryControlJob::class)->handle(app(RoomAssignmentService::class));
            $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

            Carbon::setTestNow(Carbon::parse('2026-09-29 12:01:00', config('sciem.zona_horaria')));
            app(OpenEntryControlJob::class)->handle(app(RoomAssignmentService::class));
            $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_job_abre_en_la_ventana_configurada_y_calcula_el_aula_estable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:05:00', config('sciem.zona_horaria')));
        try {
            $other = $this->createExam([
                'nombre_examen' => 'Apertura automatica',
                'fecha' => '2026-09-29',
                'hora_inicio' => '12:10',
                'hora_fin' => '13:40',
            ], [$this->aulaId]);
            DB::table('grupo_examen')->insert([
                'id_examen' => $other->id_examen,
                'id_grupo' => $this->grupoPropioId,
            ]);

            app(OpenEntryControlJob::class)->handle(app(RoomAssignmentService::class));
            $this->assertSame(Exam::EN_INGRESO, $other->fresh()->estado);
            $firstRoom = app(RoomAssignmentService::class)->assignedRoomFor(
                $other->id_examen,
                $this->student->id_estudiante
            );
            $this->assertSame($this->aulaId, (int) $firstRoom->id_ambiente);

            app(RoomAssignmentService::class)->prepare($other->id_examen);
            $this->assertSame(
                $firstRoom->id_ambiente,
                app(RoomAssignmentService::class)
                    ->assignedRoomFor($other->id_examen, $this->student->id_estudiante)
                    ->id_ambiente
            );
            $this->assertFalse(Schema::hasTable('examen_estudiante_ambiente'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_aulas_derivadas_respetan_capacidades_y_orden_de_estudiantes(): void
    {
        $other = $this->createExam(['nombre_examen' => 'Dos ambientes'], [$this->aulaId, $this->otraAulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $other->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => 1]);
        app(RoomAssignmentService::class)->prepare($other->id_examen);

        $ids = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante');
        $rooms = app(RoomAssignmentService::class);

        $this->assertSame($this->aulaId, (int) $rooms->assignedRoomFor($other->id_examen, $ids[0])->id_ambiente);
        $this->assertSame($this->otraAulaId, (int) $rooms->assignedRoomFor($other->id_examen, $ids[1])->id_ambiente);
    }

    public function test_otro_docente_no_tiene_acceso(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->postJson($this->url('verificar'), $this->input())->assertForbidden();
        $this->getJson($this->url('buscar') . '?nombre=Estudiante')->assertForbidden();
        $this->getJson($this->url('estado'))->assertForbidden();
    }
}
