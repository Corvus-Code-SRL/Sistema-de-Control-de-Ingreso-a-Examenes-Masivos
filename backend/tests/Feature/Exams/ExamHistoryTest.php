<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Security\AuditLogService;
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
        $this->getJson('/api/historial/examenes/' . $foreign->id_examen)->assertNotFound();
        $this->getJson('/api/historial/examenes/' . $scheduled->id_examen)->assertNotFound();
    }

    public function test_valida_filtros_y_sesion(): void
    {
        $this->getJson('/api/historial/examenes?materia=-1')->assertStatus(422);
        $this->actAsGuest();
        $this->getJson('/api/historial/examenes')->assertUnauthorized();
    }
}
