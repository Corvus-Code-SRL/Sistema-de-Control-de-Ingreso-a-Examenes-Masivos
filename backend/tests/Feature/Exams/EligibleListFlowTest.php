<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\EntryControl\EntryConfirmationService;
use App\Services\EntryControl\VerificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;

class EligibleListFlowTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
        $this->actAsTeacher($this->docenteId);
    }

    public function test_flujo_crear_asignar_habilitar_rechazar(): void
    {
        // 1. Crear examen con un ambiente y asignar el grupo.
        $exam = $this->createExam([], [$this->aulaId]);
        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        // 2. GET /habilitados → estudiantes HABILITADO (sin fila en examen_estudiante).
        $lista = $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->assertJsonCount(2, 'data.estudiantes')
            ->json('data.estudiantes');

        foreach ($lista as $estudiante) {
            $this->assertSame('HABILITADO', $estudiante['estado_habilitacion']);
        }

        // 3. PUT /habilitados marcando uno NO_HABILITADO con motivo.
        $noHabilitadoId = (int) $lista[0]['id_estudiante'];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $noHabilitadoId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => 'ASISTENCIA_INSUFICIENTE',
            ]],
        ])->assertOk();

        // 4. La fila existe con NO_HABILITADO.
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $noHabilitadoId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => 'ASISTENCIA_INSUFICIENTE',
        ]);

        // 5. El control de ingreso lo rechaza con el motivo.
        // Pasamos a EN_CURSO y preparamos el snapshot en Redis para que
        // VerificationService::verify() encuentre al estudiante.
        DB::table('examen')->where('id_examen', $exam->id_examen)->update(['estado' => Exam::EN_CURSO]);
        app(\App\Services\EntryControl\RoomAssignmentService::class)->prepare($exam->id_examen);

        $student = DB::table('estudiante')->where('id_estudiante', $noHabilitadoId)->first();

        $verdict = app(VerificationService::class)->verify(
            (int) $exam->id_examen,
            [
                'cod_sis' => $student->cod_sis,
                'id_ambiente' => $this->aulaId,
            ],
            User::query()->findOrFail($this->docenteId)
        );

        $this->assertSame(VerificationService::NOT_ENABLED, $verdict['veredicto']);
        $this->assertStringContainsString('Motivo: Asistencia insuficiente', $verdict['motivo']);
    }

    public function test_nomina_recargada_despues_de_asignar_aparece_habilitada(): void
    {
        $exam = $this->createExam();
        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        // 2 estudiantes en la nómina original.
        $this->assertSame(2, $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->json('count') ?? count($this->getJson("/api/examenes/{$exam->id_examen}/habilitados")->json('data.estudiantes')));

        // Simular recarga de nómina: agregar un estudiante a grupo_estudiante.
        $nuevoId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '399999999',
            'nombre' => 'Nuevo',
            'apellido_paterno' => 'Apellido',
            'estado' => 'ACTIVO',
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $nuevoId,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => 'ACTIVO',
        ]);

        $lista = $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->json('data.estudiantes');

        $this->assertCount(3, $lista);
        $nuevo = collect($lista)->firstWhere('id_estudiante', $nuevoId);
        $this->assertNotNull($nuevo);
        $this->assertSame('HABILITADO', $nuevo['estado_habilitacion']);
    }

    public function test_estudiante_agregado_despues_aparece_habilitado(): void
    {
        // Igual que el anterior, pero simulando HU-023: un estudiante suelto
        // se agrega a grupo_estudiante después de asignar el grupo.
        $exam = $this->createExam();
        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        $excepcionalId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '388888888',
            'nombre' => 'Excepcional',
            'apellido_paterno' => 'Apellido',
            'estado' => 'ACTIVO',
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $excepcionalId,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => 'ACTIVO',
        ]);

        $lista = $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->json('data.estudiantes');

        $excepcional = collect($lista)->firstWhere('id_estudiante', $excepcionalId);
        $this->assertNotNull($excepcional);
        $this->assertSame('HABILITADO', $excepcional['estado_habilitacion']);
    }

    public function test_no_habilitado_no_cuenta_como_ausente_en_reportes(): void
    {
        // Examen con un ambiente y el grupo asignado.
        $exam = $this->createExam([], [$this->aulaId]);
        $this->postJson("/api/examenes/{$exam->id_examen}/grupos", [
            'grupos' => [$this->grupoPropioId],
        ])->assertOk();

        // Dos estudiantes: uno queda NO_HABILITADO, el otro ingresa.
        $lista = $this->getJson("/api/examenes/{$exam->id_examen}/habilitados")
            ->assertOk()
            ->json('data.estudiantes');

        $noHabilitadoId = (int) $lista[0]['id_estudiante'];
        $habilitadoId = (int) $lista[1]['id_estudiante'];

        $this->putJson("/api/examenes/{$exam->id_examen}/habilitados", [
            'cambios' => [[
                'id_estudiante' => $noHabilitadoId,
                'estado_habilitacion' => 'NO_HABILITADO',
                'motivo' => 'ASISTENCIA_INSUFICIENTE',
            ]],
        ])->assertOk();

        // Pasar a FINALIZADO directo (la foto congelada conserva las filas).
        DB::table('examen')->where('id_examen', $exam->id_examen)->update(['estado' => Exam::FINALIZADO]);

        // HU-051 (reporte final).
        $reporte = app(\App\Services\Exams\FinalReportService::class)
            ->build($exam->fresh(), $this->docenteId);

        $this->assertSame(0, $reporte['asistencia']['totales']['ausentes']);

        $estudianteNoHabilitado = collect($reporte['estudiantes'])
            ->firstWhere('cod_sis', $lista[0]['codigo_sis']);
        $this->assertNotNull($estudianteNoHabilitado);
        $this->assertSame('No habilitado', $estudianteNoHabilitado['estado']);

        // HU-052 (historial).
        $historial = app(\App\Services\Exams\ExamHistoryService::class)
            ->show($exam->fresh(), $this->docenteId);

        $this->assertSame(0, $historial['totales']['ausentes']);
    }
}
