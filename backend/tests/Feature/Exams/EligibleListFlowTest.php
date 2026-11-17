<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\EntryControl\CloseOrdinaryEntryService;
use App\Services\EntryControl\EntryConfirmationService;
use App\Services\EntryControl\VerificationService;
use App\Services\Exams\ExamHistoryService;
use App\Services\Exams\ExamInProgressQueryService;
use App\Services\Exams\ExamLifecycleService;
use App\Services\Exams\FinalReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;

class EligibleListFlowTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use BuildsRosterFiles;

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

    public function test_flujo_completo_manual_y_archivo_hasta_los_totales(): void
    {
        $this->enrollStudents($this->grupoPropioId, 2);

        $zone = config('sciem.zona_horaria');
        $start = Carbon::now($zone)->subMinutes(30);
        $exam = $this->createExam([
            'fecha' => $start->toDateString(),
            'hora_inicio' => $start->format('H:i'),
            'duracion' => 120,
        ], [$this->aulaId]);
        $examId = (int) $exam->id_examen;
        $this->postJson("/api/examenes/{$examId}/grupos", ['grupos' => [$this->grupoPropioId]])->assertOk();

        $students = DB::table('estudiante')
            ->join('grupo_estudiante', 'grupo_estudiante.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('grupo_estudiante.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->get(['estudiante.id_estudiante', 'estudiante.cod_sis', 'estudiante.ci'])
            ->all();
        [$manual, $porArchivo, $entra, $ausente] = $students;

        // Manual: uno queda «No habilitado».
        $this->putJson("/api/examenes/{$examId}/habilitados", ['cambios' => [[
            'id_estudiante' => $manual->id_estudiante,
            'estado_habilitacion' => 'NO_HABILITADO',
            'motivo' => 'NO_ENTREGO_TRABAJO_FINAL',
        ]]])->assertOk();

        // Archivo: deja fuera al segundo y reemplaza el ajuste manual del primero (que sí figura).
        $rows = array_map(
            static fn (string $sis): array => [$sis, 'APELLIDO', 'NOMBRE'],
            [$manual->cod_sis, $entra->cod_sis, $ausente->cod_sis]
        );
        $token = $this->post(
            "/api/examenes/{$examId}/habilitados/preview",
            ['archivo' => $this->csvFile($rows)],
            ['Accept' => 'application/json']
        )->assertOk()->json('data.token');
        $this->postJson("/api/examenes/{$examId}/habilitados/confirm", ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.total_no_habilitados', 1)
            ->assertJsonPath('data.no_habilitados.0.id_estudiante', $porArchivo->id_estudiante);

        // El primero vuelve a quedar «No habilitado» a mano: ahora hay dos, cada uno con su motivo.
        $this->putJson("/api/examenes/{$examId}/habilitados", ['cambios' => [[
            'id_estudiante' => $manual->id_estudiante,
            'estado_habilitacion' => 'NO_HABILITADO',
            'motivo' => 'NO_ENTREGO_TRABAJO_FINAL',
        ]]])->assertOk();

        // Se abre el control de ingreso: el examen sigue PROGRAMADO y la lista deja de ser editable.
        app(RoomAssignmentService::class)->prepare($examId);
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
        $this->assertNotNull($exam->fresh()->ingreso_abierto_en);

        $this->putJson("/api/examenes/{$examId}/habilitados", ['cambios' => [[
            'id_estudiante' => $entra->id_estudiante,
            'estado_habilitacion' => 'NO_HABILITADO',
            'motivo' => 'OTRO',
            'detalle' => 'Ya con el control abierto',
        ]]])->assertStatus(409);
        $this->post(
            "/api/examenes/{$examId}/habilitados/preview",
            ['archivo' => $this->csvFile($rows)],
            ['Accept' => 'application/json']
        )->assertStatus(409);
        $this->postJson("/api/examenes/{$examId}/habilitados/confirm", ['token' => $token])->assertStatus(409);

        // Ambos son rechazados con su motivo; otro estudiante ingresa.
        $docente = User::query()->findOrFail($this->docenteId);
        $this->actingAs($docente);
        $rejected = [[$manual, 'No entregó el trabajo final'], [$porArchivo, 'No figura en la lista cargada']];

        foreach ($rejected as [$student, $reason]) {
            $this->postJson("/api/control-ingreso/examenes/{$examId}/verificar", [
                'cod_sis' => $student->cod_sis,
                'ci' => $student->ci,
                'id_ambiente' => $this->aulaId,
            ])
                ->assertOk()
                ->assertJsonPath('data.veredicto', VerificationService::NOT_ENABLED);
            $this->postJson("/api/control-ingreso/examenes/{$examId}/confirmar-ingreso", [
                'id_estudiante' => $student->id_estudiante,
                'id_ambiente' => $this->aulaId,
            ])->assertStatus(422);
        }

        $this->postJson("/api/control-ingreso/examenes/{$examId}/verificar", [
            'cod_sis' => $porArchivo->cod_sis,
            'ci' => $porArchivo->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertJsonPath('data.motivo', 'El estudiante no está habilitado para este examen. '
            . 'Motivo: No figura en la lista cargada.');

        $this->postJson("/api/control-ingreso/examenes/{$examId}/verificar", [
            'cod_sis' => $entra->cod_sis,
            'ci' => $entra->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertOk();
        $this->postJson("/api/control-ingreso/examenes/{$examId}/confirmar-ingreso", [
            'id_estudiante' => $entra->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();

        // Llega la hora de inicio: EN_CURSO, con el ingreso ordinario todavía abierto.
        $this->assertSame(1, app(ExamLifecycleService::class)->startExpired(Carbon::now($zone)));
        $this->assertSame(Exam::EN_CURSO, $exam->fresh()->estado);
        $this->putJson("/api/examenes/{$examId}/habilitados", ['cambios' => [[
            'id_estudiante' => $entra->id_estudiante,
            'estado_habilitacion' => 'NO_HABILITADO',
            'motivo' => 'OTRO',
            'detalle' => 'Ya en curso',
        ]]])->assertStatus(409);

        $inProgress = app(ExamInProgressQueryService::class)->show($exam->fresh(), $docente)['totales'];
        $this->assertSame(1, $inProgress['a_tiempo']);
        $this->assertSame(1, $inProgress['ausentes']);
        $this->assertSame(2, $inProgress['no_habilitados']);
        $this->assertSame(
            $inProgress['total'],
            $inProgress['a_tiempo'] + $inProgress['con_retraso'] + $inProgress['ausentes']
                + $inProgress['no_habilitados'] + $inProgress['expulsados']
        );

        // Se cierra el ingreso ordinario y se finaliza.
        app(CloseOrdinaryEntryService::class)->close($exam->fresh(), $docente);
        app(ExamLifecycleService::class)->finishManually($exam->fresh(), $this->docenteId);
        $this->assertSame(Exam::FINALIZADO, $exam->fresh()->estado);

        $report = app(FinalReportService::class)->build($exam->fresh(), $this->docenteId);
        $totals = $report['asistencia']['totales'];
        $this->assertSame(4, $totals['inscritos']);
        $this->assertSame(1, $totals['a_tiempo']);
        $this->assertSame(1, $totals['ausentes']);
        $this->assertSame(2, $totals['no_habilitados']);
        $this->assertSame(
            $totals['inscritos'],
            $totals['a_tiempo'] + $totals['con_retraso'] + $totals['ausentes']
                + $totals['no_habilitados'] + $totals['sin_clasificar']
        );

        $history = app(ExamHistoryService::class)->show($exam->fresh(), $this->docenteId);
        $this->assertSame(4, $history['total_estudiantes']);
        $this->assertSame(1, $history['totales']['ausentes']);
        $this->assertSame(2, $history['totales']['no_habilitados']);
        $this->assertSame(
            $history['total_estudiantes'],
            $history['totales']['a_tiempo'] + $history['totales']['con_retraso'] + $history['totales']['ausentes']
                + $history['totales']['no_habilitados'] + $history['totales']['sin_clasificar']
        );
    }
}
