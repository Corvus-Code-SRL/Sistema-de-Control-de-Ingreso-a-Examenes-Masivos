<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
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
        $exam = $this->createExam(['nombre_examen' => 'Parcial propio', 'estado' => Exam::FINALIZADO], [$this->aulaId]);
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
        $audit->registrar('MODIFICAR', 'examen', null, [
            'id_examen' => $exam->id_examen,
            'estado' => Exam::EN_INGRESO,
            'evento' => 'APERTURA_CONTROL',
        ], $this->docenteId);
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
        $this->assertContains('inicio', $types);
        $this->assertContains('ingreso', $types);
        $this->assertContains('fin', $types);
        $this->assertNull($summary->json('data.inicio_real_en'));
        $scheduledStart = collect($summary->json('data.linea_tiempo'))->firstWhere('tipo', 'inicio');
        $this->assertSame('Inicio programado del examen', $scheduledStart['descripcion']);
        $this->assertSame('Horario previsto', $scheduledStart['detalle']);
        $this->getJson('/api/historial/examenes/' . $foreign->id_examen)->assertNotFound();
        $this->getJson('/api/historial/examenes/' . $scheduled->id_examen)->assertNotFound();
    }

    public function test_muestra_inicio_real_manual_registrado_por_hu_038(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->postJson('/api/examenes/' . $exam->id_examen . '/iniciar')->assertOk();
        $this->postJson('/api/examenes/' . $exam->id_examen . '/finalizar')->assertOk();

        $startedAt = $exam->fresh()->inicio_real_en;
        $this->assertNotNull($startedAt);
        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()
            ->assertJsonPath('data.inicio_real_en', $startedAt->toIso8601String())
            ->assertJsonPath('data.inicio_automatico', false);

        $starts = collect($summary->json('data.linea_tiempo'))->where('tipo', 'inicio')->values();
        $this->assertCount(1, $starts);
        $this->assertSame('Examen iniciado', $starts[0]['descripcion']);
        $this->assertSame('Inicio manual', $starts[0]['detalle']);
        $this->assertSame(
            $startedAt->setTimezone(config('sciem.zona_horaria'))->format('Y-m-d H:i:s'),
            $starts[0]['fecha_hora']
        );
    }

    public function test_muestra_inicio_real_automatico_sin_duplicar_el_hito(): void
    {
        $exam = $this->createExam(['fecha' => '2026-10-28', 'estado' => Exam::FINALIZADO]);
        $startedAt = Carbon::parse('2026-10-28 08:10:00', config('sciem.zona_horaria'));
        $exam->forceFill([
            'inicio_real_en' => $startedAt->copy()->utc(),
            'inicio_automatico' => true,
        ])->save();

        $summary = $this->getJson('/api/historial/examenes/' . $exam->id_examen)
            ->assertOk()
            ->assertJsonPath('data.inicio_automatico', true);

        $starts = collect($summary->json('data.linea_tiempo'))->where('tipo', 'inicio')->values();
        $this->assertCount(1, $starts);
        $this->assertSame('Examen iniciado', $starts[0]['descripcion']);
        $this->assertSame('Inicio automático', $starts[0]['detalle']);
        $this->assertSame('2026-10-28 08:10:00', $starts[0]['fecha_hora']);
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
        $this->assertSame('Finalización manual', $closures[0]['detalle']);
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

    public function test_valida_filtros_y_sesion(): void
    {
        $this->getJson('/api/historial/examenes?materia=-1')->assertStatus(422);
        $this->actAsGuest();
        $this->getJson('/api/historial/examenes')->assertUnauthorized();
    }
}
