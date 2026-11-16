<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamHistoryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    public function test_lista_y_resumen_solo_de_examenes_finalizados_propios(): void
    {
        $exam = $this->createExam(
            array_merge(['nombre_examen' => 'Parcial propio', 'estado' => Exam::FINALIZADO], $this->entryOpen(true)),
            [$this->aulaId]
        );
        $foreign = $this->createExam(['estado' => Exam::FINALIZADO, 'id_usuario_docente' => $this->otroDocenteId]);
        $scheduled = $this->createExam(['estado' => Exam::PROGRAMADO]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $students = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->pluck('id_estudiante');
        foreach (['INGRESO', 'NO_INGRESO'] as $index => $state) {
            DB::table('examen_estudiante')->insert([
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $students[$index],
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => $state,
                'hora_ingreso' => $state === 'INGRESO' ? '08:05:00' : null,
                'registrado_en' => $state === 'INGRESO' ? now() : null,
                'id_ambiente' => $state === 'INGRESO' ? $this->aulaId : null,
            ]);
        }

        $audit = app(AuditLogService::class);
        $audit->registrar('FINALIZAR', 'examen', null, [
            'id_examen' => $exam->id_examen,
            'estado' => Exam::FINALIZADO,
        ], $this->docenteId);

        $this->getJson('/api/historial/examenes?buscar=Parcial')
            ->assertOk()->assertJsonCount(1, 'data.examenes')
            ->assertJsonPath('data.examenes.0.esperados', 2)
            ->assertJsonPath('data.examenes.0.presentes', 1);
        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()->assertJsonPath('data.totales.a_tiempo', 1)
            ->assertJsonPath('data.totales.ausentes', 1)
            ->assertJsonPath('data.ambientes.0.presentes', 1);
        $types = array_column($summary->json('data.linea_tiempo'), 'tipo');
        $this->assertContains('apertura', $types);
        $this->assertContains('cierre_ingreso', $types);
        $this->assertContains('inicio', $types);
        $this->assertNotContains('ingreso', $types, 'los ingresos puntuales no se listan');
        $this->assertContains('fin', $types);
        $this->assertNull($summary->json('data.inicio_real_en'));
        $scheduledStart = collect($summary->json('data.linea_tiempo'))->firstWhere('tipo', 'inicio');
        $this->assertSame('Inicio programado del examen', $scheduledStart['descripcion']);
        $this->assertSame('Horario previsto', $scheduledStart['detalle']);
        $this->getJson('/api/historial/examenes/' . $foreign->id_examen)->assertForbidden()->assertJsonPath('message', 'No tiene acceso a este examen.');
        $this->getJson('/api/historial/examenes/' . $scheduled->id_examen)->assertNotFound();
    }

    public function test_el_inicio_automatico_no_registra_inicio_real_y_se_muestra_el_horario_programado(): void
    {
        $exam = $this->createExam(['estado' => Exam::PROGRAMADO], [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
        $startsAt = app(\App\Services\Exams\ExamTimingService::class)->startsAt($exam);
        app(\App\Services\Exams\ExamLifecycleService::class)->startExpired($startsAt);
        $this->postJson('/api/examenes/' . $exam->id_examen . '/finalizar')->assertOk();

        $fresh = $exam->fresh();
        $this->assertNull($fresh->inicio_real_en);
        $this->assertNotNull($fresh->ingreso_abierto_en);
        $this->assertNotNull($fresh->ingreso_ordinario_cerrado_en);
        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()
            ->assertJsonPath('data.ingreso_abierto_en', $fresh->ingreso_abierto_en->toIso8601String());
        $this->assertArrayNotHasKey('inicio_real_en', $summary->json('data'));
        $this->assertArrayNotHasKey('inicio_automatico', $summary->json('data'));

        $timeline = collect($summary->json('data.linea_tiempo'));
        $starts = $timeline->where('tipo', 'inicio')->values();
        $this->assertCount(1, $starts);
        $this->assertSame('Inicio programado del examen', $starts[0]['descripcion']);
        $this->assertSame('Horario previsto', $starts[0]['detalle']);
        $this->assertSame('Apertura del control', $timeline->firstWhere('tipo', 'apertura')['descripcion']);
        $closing = $timeline->firstWhere('tipo', 'cierre_ingreso');
        $this->assertSame('Cierre del ingreso ordinario', $closing['descripcion']);
        $this->assertSame('Al terminar el examen', $closing['detalle']);
    }

    public function test_un_inicio_real_antiguo_no_reemplaza_al_horario_programado(): void
    {
        $exam = $this->createExam(['fecha' => '2026-10-28', 'estado' => Exam::FINALIZADO]);
        $exam->forceFill(['inicio_real_en' => '2026-10-28 12:10:00+00', 'inicio_automatico' => true])->save();

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)->assertOk();
        $this->assertArrayNotHasKey('inicio_real_en', $summary->json('data'));

        $starts = collect($summary->json('data.linea_tiempo'))->where('tipo', 'inicio')->values();
        $this->assertCount(1, $starts);
        $this->assertSame('Inicio programado del examen', $starts[0]['descripcion']);
        $this->assertSame('2026-10-28 08:00:00', $starts[0]['fecha_hora']);
    }

    public function test_usa_fin_real_y_tipo_automatico_sin_duplicar_el_cierre(): void
    {
        $exam = $this->createExam(['fecha' => '2026-10-28', 'estado' => Exam::FINALIZADO]);
        $finishedAt = Carbon::parse('2026-10-28 09:41:00', config('sciem.zona_horaria'));
        $exam->forceFill([
            'fin_real_en' => $finishedAt->copy()->utc(),
            'fin_automatico' => true,
        ])->save();
        app(AuditLogService::class)->registrar('FINALIZAR', 'examen', null, [
            'id_examen' => $exam->id_examen,
            'estado' => Exam::FINALIZADO,
        ], $this->docenteId);

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()
            ->assertJsonPath('data.fin_real_en', $exam->fresh()->fin_real_en->toIso8601String())
            ->assertJsonPath('data.fin_automatico', true);

        $closures = collect($summary->json('data.linea_tiempo'))->where('tipo', 'fin')->values();
        $this->assertCount(1, $closures);
        $this->assertSame('2026-10-28 09:41:00', $closures[0]['fecha_hora']);
        $this->assertSame('Automático', $closures[0]['detalle']);
    }

    public function test_muestra_fin_real_aunque_no_exista_auditoria_antigua(): void
    {
        $exam = $this->createExam(['fecha' => '2026-10-28', 'estado' => Exam::FINALIZADO]);
        $exam->forceFill([
            'fin_real_en' => Carbon::parse('2026-10-28 09:15:00', config('sciem.zona_horaria'))->utc(),
            'fin_automatico' => false,
        ])->save();

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)->assertOk();
        $closures = collect($summary->json('data.linea_tiempo'))->where('tipo', 'fin')->values();
        $this->assertCount(1, $closures);
        $this->assertSame('2026-10-28 09:15:00', $closures[0]['fecha_hora']);
        $this->assertSame('Responsable no registrado', $closures[0]['detalle']);
    }

    public function test_mantiene_cierre_automatico_auditado_sin_fin_real(): void
    {
        $exam = $this->createExam(['estado' => Exam::FINALIZADO]);
        app(AuditLogService::class)->registrar('FINALIZAR', 'examen', null, [
            'id_examen' => $exam->id_examen,
            'estado' => Exam::FINALIZADO,
            'tipo_finalizacion' => 'AUTOMATICA',
        ], $this->docenteId);

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()
            ->assertJsonPath('data.fin_real_en', null);
        $closures = collect($summary->json('data.linea_tiempo'))->where('tipo', 'fin')->values();
        $this->assertCount(1, $closures);
        $this->assertSame('Automático', $closures[0]['detalle']);
    }

    public function test_lista_ordenada_filtrada_y_paginada_solo_con_finalizados_del_docente(): void
    {
        $first = $this->createExam([
            'nombre_examen' => 'Examen diagnóstico',
            'fecha' => '2026-10-01',
            'estado' => Exam::FINALIZADO,
        ]);
        $second = $this->createExam([
            'nombre_examen' => 'Álgebra final',
            'fecha' => '2026-10-02',
            'id_materia' => $this->basesDatosId,
            'estado' => Exam::FINALIZADO,
        ]);
        $third = $this->createExam([
            'nombre_examen' => 'Cálculo final',
            'fecha' => '2026-10-03',
            'estado' => Exam::FINALIZADO,
        ]);
        $this->createExam(['nombre_examen' => 'Programado', 'estado' => Exam::PROGRAMADO]);
        $this->createExam(['nombre_examen' => 'En curso', 'estado' => Exam::EN_CURSO]);
        $this->createExam(['nombre_examen' => 'Ajeno', 'estado' => Exam::FINALIZADO,
            'id_usuario_docente' => $this->otroDocenteId]);

        $list = $this->getJson('/api/historial/examenes')->assertOk()->assertJsonCount(3, 'data.examenes');
        $this->assertSame(
            [$third->id_examen, $second->id_examen, $first->id_examen],
            array_column($list->json('data.examenes'), 'id_examen')
        );
        $this->getJson('/api/historial/examenes?materia=' . $this->calculoId)
            ->assertOk()->assertJsonCount(2, 'data.examenes');
        $this->getJson('/api/historial/examenes?buscar=diagnostico')
            ->assertOk()->assertJsonCount(1, 'data.examenes')
            ->assertJsonPath('data.examenes.0.id_examen', $first->id_examen);

        for ($index = 0; $index < 22; $index++) {
            $this->createExam(['estado' => Exam::FINALIZADO,
                'nombre_examen' => 'Adicional ' . $index, 'fecha' => '2026-10-04']);
        }
        $this->getJson('/api/historial/examenes?page=1')->assertOk()
            ->assertJsonCount(10, 'data.examenes')->assertJsonPath('data.total', 25)
            ->assertJsonPath('data.paginas', 3);
        $last = $this->getJson('/api/historial/examenes?page=3')->assertOk()
            ->assertJsonCount(5, 'data.examenes')->assertJsonPath('data.pagina', 3);
        $this->assertSame($first->id_examen, $last->json('data.examenes.4.id_examen'));
    }

    public function test_resumen_ordena_hitos_y_muestra_controladores_reales_por_ambiente(): void
    {
        $exam = $this->createExam([
            'fecha' => '2026-10-28',
            'estado' => Exam::FINALIZADO,
        ], [$this->aulaId]);
        $zone = config('sciem.zona_horaria');
        $exam->forceFill([
            'ingreso_abierto_en' => Carbon::parse('2026-10-28 07:30:00', $zone)->utc(),
            'ingreso_ordinario_cerrado_en' => Carbon::parse('2026-10-28 08:15:00', $zone)->utc(),
            'fin_real_en' => Carbon::parse('2026-10-28 09:30:00', $zone)->utc(),
            'fin_automatico' => false,
        ])->save();
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId]);
        $students = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->pluck('id_estudiante');
        foreach (['INGRESO', 'CON_RETRASO'] as $index => $state) {
            DB::table('examen_estudiante')->insert([
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $students[$index],
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => $state,
                'hora_ingreso' => $index === 0 ? '08:05:00' : '08:20:00',
                'registrado_en' => now(),
                'id_ambiente' => $this->aulaId,
                'id_usuario_controlador' => $this->docenteId,
            ]);
        }
        $typeId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Copia'], 'id_falta');
        DB::table('reporte_estudiante')->insert([
            'id_estudiante' => $students[0],
            'id_examen' => $exam->id_examen,
            'id_tipo_falta' => $typeId,
            'id_usuario_reportante' => $this->docenteId,
            'evidencia_url' => 'https://example.test/evidencia',
            'evidencia_public_id' => 'evidencia-prueba',
            'fecha_reporte' => '2026-10-28 08:10:00-04',
            'estado' => 'PENDIENTE',
        ]);
        $audit = app(AuditLogService::class);
        foreach ([
            ['MODIFICAR', '08:15:00', ['evento' => 'CIERRE_INGRESO_ORDINARIO']],
            ['FINALIZAR', '09:30:00', []],
        ] as [$action, $hour, $extra]) {
            $audit->registrar($action, 'examen', null, array_merge([
                'id_examen' => $exam->id_examen,
                'estado' => $action === 'FINALIZAR' ? Exam::FINALIZADO : Exam::EN_CURSO,
            ], $extra), $this->docenteId);
            DB::table('log')->where('id_log', DB::table('log')->max('id_log'))
                ->update(['fecha_hora' => '2026-10-28 ' . $hour . '-04']);
        }

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)->assertOk()
            ->assertJsonPath('data.totales.a_tiempo', 1)
            ->assertJsonPath('data.totales.con_retraso', 1)
            ->assertJsonPath('data.totales.ausentes', 0)
            ->assertJsonPath('data.totales.sin_clasificar', 0)
            ->assertJsonPath('data.totales.incidencias', 1)
            ->assertJsonPath('data.ambientes.0.presentes', 2)
            ->assertJsonPath('data.ambientes.0.auxiliares.0', 'Ana Rojas');
        $timeline = $summary->json('data.linea_tiempo');
        $this->assertSame(
            ['apertura', 'inicio', 'incidencia', 'cierre_ingreso', 'retraso', 'fin'],
            array_column($timeline, 'tipo')
        );
        $this->assertSame('Automático', $timeline[0]['detalle']);
        $this->assertSame('Horario previsto', $timeline[1]['detalle']);
        $this->assertStringContainsString('Reportó: Ana Rojas', $timeline[2]['detalle']);
        $this->assertSame('Ana Rojas', $timeline[3]['detalle']);
        $this->assertSame('08:20:00', substr($timeline[4]['fecha_hora'], 11));
        $this->assertSame('Registró: Ana Rojas', $timeline[4]['detalle']);
        $this->assertSame('Ana Rojas', $timeline[5]['detalle']);
    }

    public function test_la_linea_de_tiempo_distingue_la_incidencia_retirada_de_la_central(): void
    {
        $exam = $this->createExam(['estado' => Exam::FINALIZADO], [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $students = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->pluck('id_estudiante');
        $typeId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Suplantación'], 'id_falta');
        $recordIds = [];
        foreach ([0, 1] as $index) {
            $reportId = DB::table('reporte_estudiante')->insertGetId([
                'id_estudiante' => $students[$index],
                'id_examen' => $exam->id_examen,
                'id_tipo_falta' => $typeId,
                'id_usuario_reportante' => $this->docenteId,
                'id_usuario_revisor' => $this->docenteId,
                'evidencia_url' => 'https://example.test/evidencia',
                'evidencia_public_id' => 'evidencia-prueba',
                'fecha_reporte' => '2026-10-28 08:1' . $index . ':00-04',
                'fecha_revision' => '2026-10-28 09:00:00-04',
                'estado' => 'APROBADO',
            ], 'id_reporte_est');
            $recordIds[$index] = DB::table('central_riesgo')->insertGetId([
                'id_reporte_est' => $reportId,
                'id_estudiante' => $students[$index],
                'fecha_registro' => '2026-10-28 09:00:00-04',
            ], 'id_cr_estudiante');
        }
        DB::table('central_riesgo')->where('id_cr_estudiante', $recordIds[1])->update([
            'rectificado_en' => '2026-11-02 10:00:00-04',
            'id_usuario_rectificador' => $this->docenteId,
            'motivo_rectificacion' => 'Registro retirado por error de identificación.',
        ]);

        $details = collect($this->getJson('/api/historial/examenes/' . $exam->id_examen)->assertOk()
            ->json('data.linea_tiempo'))->where('tipo', 'incidencia')->pluck('detalle')->all();

        $this->assertCount(2, $details);
        $this->assertStringStartsWith('Aprobada · en Central de Riesgo', $details[0]);
        $this->assertStringStartsWith('Aprobada · retirada de la Central de Riesgo', $details[1]);
    }

    public function test_un_retraso_autorizado_muestra_a_quien_lo_autorizo(): void
    {
        $exam = $this->createExam(['estado' => Exam::FINALIZADO], [$this->aulaId]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $studentId = (int) DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->value('id_estudiante');
        $assistantId = $this->docenteId;
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'CON_RETRASO',
            'hora_ingreso' => '08:20:00',
            'registrado_en' => now(),
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $assistantId,
        ]);
        DB::table('solicitud_ingreso_retraso')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_ambiente' => $this->aulaId,
            'id_usuario_solicitante' => $assistantId,
            'id_usuario_resolutor' => $this->otroDocenteId,
            'estado' => 'AUTORIZADA',
            'solicitado_en' => now(),
            'resuelto_en' => now(),
        ]);

        $late = collect($this->getJson('/api/historial/examenes/' . $exam->id_examen)->assertOk()
            ->json('data.linea_tiempo'))->where('tipo', 'retraso')->values();

        $this->assertCount(1, $late);
        $this->assertStringStartsWith('Autorizó: ', $late[0]['detalle']);
        $this->assertStringNotContainsString('Ana Rojas', $late[0]['detalle']);
    }

    public function test_las_incidencias_no_sacan_al_estudiante_de_su_categoria_de_asistencia(): void
    {
        $exam = $this->createExam(['estado' => Exam::FINALIZADO]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        $this->enrollStudents($this->grupoPropioId, 1);
        $students = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->pluck('id_estudiante');
        foreach ($students as $index => $studentId) {
            DB::table('examen_estudiante')->insert([
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $studentId,
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => $index === 0 ? 'INGRESO' : 'NO_INGRESO',
                'hora_ingreso' => $index === 0 ? '08:05:00' : null,
                'registrado_en' => $index === 0 ? now() : null,
            ]);
        }
        $this->enrollStudents($this->grupoAjenoId, 1);
        $outsider = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoAjenoId)
            ->value('id_estudiante');
        $typeId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Copia'], 'id_falta');
        foreach ([$students[0], $students[1], $students[1], $outsider] as $studentId) {
            DB::table('reporte_estudiante')->insert([
                'id_estudiante' => $studentId,
                'id_examen' => $exam->id_examen,
                'id_tipo_falta' => $typeId,
                'id_usuario_reportante' => $this->docenteId,
                'evidencia_url' => 'https://example.test/evidencia',
                'evidencia_public_id' => 'evidencia-prueba',
                'fecha_reporte' => now(),
                'estado' => 'PENDIENTE',
            ]);
        }

        $this->getJson('/api/historial/examenes')->assertOk()
            ->assertJsonPath('data.examenes.0.esperados', 3)
            ->assertJsonPath('data.examenes.0.presentes', 1)
            ->assertJsonPath('data.examenes.0.incidencias', 4);
        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()
            ->assertJsonPath('data.totales.a_tiempo', 1)
            ->assertJsonPath('data.totales.con_retraso', 0)
            ->assertJsonPath('data.totales.ausentes', 2)
            ->assertJsonPath('data.totales.sin_clasificar', 0)
            ->assertJsonPath('data.totales.incidencias', 4)
            ->assertJsonPath('data.total_estudiantes', 3);
        $this->assertSame(3, $this->classifiedTotal($summary->json('data.totales')));
        $this->assertCount(4, collect($summary->json('data.linea_tiempo'))
            ->where('tipo', 'incidencia'));
    }

    public function test_un_estado_desconocido_o_nulo_cuenta_como_sin_clasificar_y_no_como_ausente(): void
    {
        $exam = $this->createExam(['estado' => Exam::FINALIZADO]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $students = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->pluck('id_estudiante');
        // El estado es NOT NULL y de tres valores; el DDL es transaccional y se revierte con la prueba.
        DB::statement('ALTER TABLE examen_estudiante ALTER COLUMN estado_ingreso DROP NOT NULL');
        foreach ([null, 'NO_INGRESO'] as $index => $state) {
            DB::table('examen_estudiante')->insert([
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $students[$index],
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => $state,
            ]);
        }

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)->assertOk()
            ->assertJsonPath('data.totales.ausentes', 1)
            ->assertJsonPath('data.totales.sin_clasificar', 1)
            ->assertJsonPath('data.total_estudiantes', 2);
        $this->assertSame(2, $this->classifiedTotal($summary->json('data.totales')));
        $this->getJson('/api/historial/examenes')->assertOk()
            ->assertJsonPath('data.examenes.0.presentes', 0);
    }

    public function test_los_esperados_salen_de_examen_estudiante_y_no_de_las_inscripciones_actuales(): void
    {
        $exam = $this->createExam(['estado' => Exam::FINALIZADO]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $this->grupoPropioId]);
        $this->enrollStudents($this->grupoPropioId, 3);
        $studentId = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')->value('id_estudiante');
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)->assertOk()
            ->assertJsonPath('data.total_estudiantes', 1)
            ->assertJsonPath('data.totales.ausentes', 1);
        $this->assertSame(1, $this->classifiedTotal($summary->json('data.totales')));
        $this->getJson('/api/historial/examenes')->assertOk()
            ->assertJsonPath('data.examenes.0.esperados', 1);
    }

    /** Puntuales + con retraso + ausentes + sin clasificar: las incidencias no suman. */
    private function classifiedTotal(array $totals): int
    {
        return $totals['a_tiempo'] + $totals['con_retraso'] + $totals['ausentes'] + $totals['sin_clasificar'];
    }

    public function test_sin_nomina_congelada_usa_las_inscripciones_actuales_como_ausentes(): void
    {
        $exam = $this->createExam(['estado' => Exam::FINALIZADO]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        $studentId = DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)
            ->value('id_estudiante');
        $typeId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Copia'], 'id_falta');
        DB::table('reporte_estudiante')->insert([
            'id_estudiante' => $studentId,
            'id_examen' => $exam->id_examen,
            'id_tipo_falta' => $typeId,
            'id_usuario_reportante' => $this->docenteId,
            'evidencia_url' => 'https://example.test/evidencia',
            'evidencia_public_id' => 'evidencia-prueba',
            'fecha_reporte' => now(),
            'estado' => 'PENDIENTE',
        ]);

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()
            ->assertJsonPath('data.totales.a_tiempo', 0)
            ->assertJsonPath('data.totales.con_retraso', 0)
            ->assertJsonPath('data.totales.ausentes', 2)
            ->assertJsonPath('data.totales.incidencias', 1)
            ->assertJsonPath('data.total_estudiantes', 2);
        $this->assertSame(2, $this->classifiedTotal($summary->json('data.totales')));
    }

    public function test_historial_vacio_y_examen_ajeno(): void
    {
        $foreign = $this->createExam(['estado' => Exam::FINALIZADO,
            'id_usuario_docente' => $this->otroDocenteId]);
        $this->getJson('/api/historial/examenes')->assertOk()
            ->assertJsonPath('data.total', 0)->assertJsonCount(0, 'data.examenes');
        $this->getJson('/api/historial/examenes/' . $foreign->id_examen)
            ->assertForbidden()->assertJsonPath('message', 'No tiene acceso a este examen.');
    }

    public function test_valida_filtros_y_sesion(): void
    {
        $this->getJson('/api/historial/examenes?materia=-1')->assertStatus(422);
        $this->actAsGuest();
        $this->getJson('/api/historial/examenes')->assertUnauthorized();
    }
}
