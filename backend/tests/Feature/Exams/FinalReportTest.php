<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Exams\FinalReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Reporte final del examen (HU-051): cabecera, asistencia por grupo, estudiantes,
 * incidencias, PDF y restricción de acceso.
 */
class FinalReportTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private const UNAVAILABLE = 'El reporte final solo está disponible para sus exámenes finalizados.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
        $this->actAsTeacher($this->docenteId);
    }

    private function url(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}/reporte-final";
    }

    /** Examen finalizado con un estudiante que ingresó a tiempo y otro ausente. */
    private function finishedExam(array $overrides = []): Exam
    {
        $exam = $this->createExam(array_merge(['estado' => Exam::FINALIZADO], $overrides), [$this->aulaId]);

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentIds = $this->groupStudentIds();

        DB::table('examen_estudiante')->insert([
            [
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $studentIds[0],
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => 'INGRESO',
                'hora_ingreso' => '08:05',
                'id_ambiente' => $this->aulaId,
            ],
            [
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $studentIds[1],
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => 'NO_INGRESO',
                'hora_ingreso' => null,
                'id_ambiente' => null,
            ],
        ]);

        return $exam;
    }

    private function groupStudentIds(): array
    {
        return DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->all();
    }

    public function test_docente_ve_cabecera_asistencia_y_estudiantes_del_examen_finalizado(): void
    {
        $exam = $this->finishedExam(['nombre_examen' => 'Parcial final']);

        $response = $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.cabecera.nombre_examen', 'Parcial final')
            ->assertJsonPath('data.cabecera.hora_inicio', '08:00 (programada)')
            ->assertJsonPath('data.asistencia.totales.inscritos', 2)
            ->assertJsonPath('data.asistencia.totales.a_tiempo', 1)
            ->assertJsonPath('data.asistencia.totales.con_retraso', 0)
            ->assertJsonPath('data.asistencia.totales.ausentes', 1)
            ->assertJsonCount(1, 'data.asistencia.grupos')
            ->assertJsonCount(2, 'data.estudiantes');

        $this->assertEqualsCanonicalizing(
            ['A tiempo', 'Ausente'],
            array_column($response->json('data.estudiantes'), 'estado')
        );
    }

    public function test_encabezado_usa_las_horas_reales_en_hora_de_la_paz(): void
    {
        $exam = $this->finishedExam();

        DB::table('examen')->where('id_examen', $exam->id_examen)->update([
            'inicio_real_en' => '2026-10-28 12:14:00+00',
            'fin_real_en' => '2026-10-28 13:52:00+00',
        ]);

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.cabecera.hora_inicio', '08:14')
            ->assertJsonPath('data.cabecera.hora_fin', '09:52');
    }

    public function test_si_falta_una_hora_real_muestra_la_programada_con_su_etiqueta(): void
    {
        $exam = $this->finishedExam();

        DB::table('examen')->where('id_examen', $exam->id_examen)->update([
            'inicio_real_en' => '2026-10-28 12:14:00+00',
        ]);

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.cabecera.hora_inicio', '08:14')
            ->assertJsonPath('data.cabecera.hora_fin', '09:30 (programada)');
    }

    public function test_sin_incidencias_la_lista_viene_vacia(): void
    {
        $exam = $this->finishedExam();

        $response = $this->getJson($this->url($exam))->assertOk();

        $this->assertSame([], $response->json('data.incidencias'));
    }

    public function test_incidencias_muestran_tipo_y_decision_del_docente(): void
    {
        $exam = $this->finishedExam();
        $studentId = $this->groupStudentIds()[0];
        $typeId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Copia'], 'id_falta');

        DB::table('reporte_estudiante')->insert([
            'id_estudiante' => $studentId,
            'id_examen' => $exam->id_examen,
            'id_tipo_falta' => $typeId,
            'id_usuario_reportante' => $this->docenteId,
            'descripcion' => 'Se observó material no permitido.',
            'fecha_reporte' => now(),
            'estado' => 'APROBADO',
            'observacion_revision' => 'Se anula la prueba.',
            'fecha_revision' => now(),
            'id_usuario_revisor' => $this->docenteId,
        ]);

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonCount(1, 'data.incidencias')
            ->assertJsonPath('data.incidencias.0.tipo', 'Copia')
            ->assertJsonPath('data.incidencias.0.decision', 'Aprobada')
            ->assertJsonPath('data.incidencias.0.observacion', 'Se anula la prueba.');
    }

    public function test_no_se_genera_reporte_de_un_examen_en_curso(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);

        $this->getJson($this->url($exam))
            ->assertForbidden()
            ->assertJsonPath('message', self::UNAVAILABLE);
    }

    public function test_un_docente_no_ve_el_reporte_de_otro_docente(): void
    {
        $exam = $this->finishedExam(['id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson($this->url($exam))
            ->assertForbidden()
            ->assertJsonPath('message', self::UNAVAILABLE);
    }

    public function test_id_no_numerico_responde_404(): void
    {
        $this->getJson('/api/examenes/abc/reporte-final')->assertNotFound();
    }

    public function test_docente_descarga_el_pdf_con_nombre_de_examen_y_fecha(): void
    {
        $exam = $this->finishedExam(['nombre_examen' => 'Parcial final']);

        $response = $this->get($this->url($exam) . '/pdf')->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'reporte-final-parcial-final-' . $exam->fecha->toDateString() . '.pdf',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_no_se_descarga_el_pdf_de_un_examen_en_curso(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);

        $this->getJson($this->url($exam) . '/pdf')->assertForbidden();
    }

    public function test_el_pdf_de_un_examen_con_1000_estudiantes_no_agota_la_memoria(): void
    {
        $exam = $this->finishedExam();
        $this->enrollStudents($this->grupoPropioId, 998);

        $rows = [];

        foreach (array_slice($this->groupStudentIds(), 2) as $studentId) {
            $rows[] = [
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $studentId,
                'id_grupo' => $this->grupoPropioId,
                'estado_habilitacion' => 'HABILITADO',
                'estado_ingreso' => 'INGRESO',
                'hora_ingreso' => '08:05',
                'id_ambiente' => $this->aulaId,
            ];
        }

        DB::table('examen_estudiante')->insert($rows);

        $response = $this->get($this->url($exam) . '/pdf')->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_un_estado_de_ingreso_desconocido_no_rompe_las_sumas_por_grupo(): void
    {
        $rows = collect(['INGRESO', 'CON_RETRASO', 'NO_INGRESO', 'ESTADO_NUEVO'])
            ->map(fn (string $state, int $index) => (object) [
                'id_grupo' => 1,
                'num_grupo' => '1',
                'estado_ingreso' => $state,
                'hora_ingreso' => null,
                'cod_sis' => '20260000' . $index,
                'nombre' => 'Ana',
                'apellido_paterno' => 'Rojas',
                'apellido_materno' => 'Paz',
                'nro_aula' => null,
            ]);

        $service = app(FinalReportService::class);

        $attendance = $this->callPrivate($service, 'attendanceByGroup', [$rows]);
        $group = $attendance['grupos'][0];

        $this->assertSame(4, $group['inscritos']);
        $this->assertSame(1, $group['sin_clasificar']);
        $this->assertSame(
            $group['inscritos'],
            $group['a_tiempo'] + $group['con_retraso'] + $group['ausentes'] + $group['sin_clasificar']
        );
        $this->assertSame(1, $attendance['totales']['sin_clasificar']);

        $students = $this->callPrivate($service, 'studentList', [$rows]);
        $this->assertContains('Sin clasificar', array_column($students, 'estado'));
    }

    public function test_las_sumas_cuadran_en_dos_grupos_de_35_y_20_inscritos(): void
    {
        $countsByGroup = [
            1 => ['INGRESO' => 21, 'CON_RETRASO' => 7, 'NO_INGRESO' => 7],
            2 => ['INGRESO' => 12, 'CON_RETRASO' => 4, 'NO_INGRESO' => 4],
        ];

        $rows = collect();

        foreach ($countsByGroup as $groupId => $counts) {
            foreach ($counts as $state => $quantity) {
                for ($i = 0; $i < $quantity; $i++) {
                    $rows->push((object) [
                        'id_grupo' => $groupId,
                        'num_grupo' => (string) $groupId,
                        'estado_ingreso' => $state,
                    ]);
                }
            }
        }

        $attendance = $this->callPrivate(app(FinalReportService::class), 'attendanceByGroup', [$rows]);

        $this->assertCount(2, $attendance['grupos']);

        $keys = ['inscritos', 'a_tiempo', 'con_retraso', 'ausentes', 'sin_clasificar'];
        $expected = [[35, 21, 7, 7, 0], [20, 12, 4, 4, 0]];

        foreach ($attendance['grupos'] as $index => $group) {
            $this->assertSame(
                array_combine($keys, $expected[$index]),
                array_intersect_key($group, array_flip($keys))
            );
            $this->assertSame(
                $group['inscritos'],
                $group['a_tiempo'] + $group['con_retraso'] + $group['ausentes'] + $group['sin_clasificar']
            );
        }

        $totals = $attendance['totales'];

        $this->assertSame(array_combine($keys, [55, 33, 11, 11, 0]), $totals);
    }

    private function callPrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
