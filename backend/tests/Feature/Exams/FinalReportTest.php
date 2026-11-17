<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Exams\FinalReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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
        $exam = $this->createExam(array_merge([
            'estado' => Exam::FINALIZADO,
            'ingreso_abierto_en' => '2026-10-28 11:30:00+00',
            'ingreso_ordinario_cerrado_en' => '2026-10-28 12:15:00+00',
        ], $overrides), [$this->aulaId]);

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
            ->assertJsonPath('data.cabecera.hora_inicio', '08:00')
            ->assertJsonPath('data.cabecera.hora_apertura', '07:30')
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

    public function test_un_estudiante_expulsado_figura_como_expulsado_con_su_hora(): void
    {
        $exam = $this->finishedExam();
        $expelledId = $this->groupStudentIds()[0];

        DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $expelledId)
            ->update(['expulsado_en' => '2026-10-28 12:41:00+00']);

        $response = $this->getJson($this->url($exam))->assertOk();

        $this->assertEqualsCanonicalizing(
            ['Expulsado · 08:41', 'Ausente'],
            array_column($response->json('data.estudiantes'), 'estado')
        );
    }

    public function test_encabezado_usa_la_hora_programada_la_apertura_y_el_fin_real_en_hora_de_la_paz(): void
    {
        $exam = $this->finishedExam();

        DB::table('examen')->where('id_examen', $exam->id_examen)->update([
            'inicio_real_en' => '2026-10-28 12:14:00+00',
            'fin_real_en' => '2026-10-28 13:52:00+00',
        ]);

        // inicio_real_en ya no se usa: el inicio es el programado.
        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.cabecera.hora_inicio', '08:00')
            ->assertJsonPath('data.cabecera.hora_apertura', '07:30')
            ->assertJsonPath('data.cabecera.hora_fin', '09:52');
    }

    public function test_si_falta_el_fin_real_muestra_la_hora_programada_con_su_etiqueta(): void
    {
        $exam = $this->finishedExam();

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.cabecera.hora_inicio', '08:00')
            ->assertJsonPath('data.cabecera.hora_fin', '09:30 (programada)');
    }

    public function test_sin_apertura_registrada_la_cabecera_no_inventa_la_hora(): void
    {
        $exam = $this->finishedExam(['ingreso_abierto_en' => null, 'ingreso_ordinario_cerrado_en' => null]);

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.cabecera.hora_apertura', null);
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

        $keys = ['inscritos', 'a_tiempo', 'con_retraso', 'ausentes', 'no_habilitados', 'sin_clasificar'];
        $expected = [[35, 21, 7, 7, 0, 0], [20, 12, 4, 4, 0, 0]];

        foreach ($attendance['grupos'] as $index => $group) {
            $this->assertSame(
                array_combine($keys, $expected[$index]),
                array_intersect_key($group, array_flip($keys))
            );
            $this->assertSame(
                $group['inscritos'],
                $group['a_tiempo'] + $group['con_retraso'] + $group['ausentes']
                    + $group['no_habilitados'] + $group['sin_clasificar']
            );
        }

        $totals = $attendance['totales'];

        $this->assertSame(array_combine($keys, [55, 33, 11, 11, 0, 0]), $totals);
    }

    public function test_los_no_habilitados_tienen_su_propio_total_y_no_son_ausentes(): void
    {
        $rows = collect();

        foreach ([
            ['INGRESO', 'HABILITADO', 3],
            ['CON_RETRASO', 'HABILITADO', 1],
            ['NO_INGRESO', 'HABILITADO', 2],
            ['NO_INGRESO', 'NO_HABILITADO', 4],
        ] as [$entry, $enabled, $quantity]) {
            for ($i = 0; $i < $quantity; $i++) {
                $rows->push((object) [
                    'id_grupo' => 1,
                    'num_grupo' => '1',
                    'estado_ingreso' => $entry,
                    'estado_habilitacion' => $enabled,
                ]);
            }
        }

        $totals = $this->callPrivate(app(FinalReportService::class), 'attendanceByGroup', [$rows])['totales'];

        $this->assertSame(10, $totals['inscritos']);
        $this->assertSame(2, $totals['ausentes']);
        $this->assertSame(4, $totals['no_habilitados']);
        $this->assertSame(
            $totals['inscritos'],
            $totals['a_tiempo'] + $totals['con_retraso'] + $totals['ausentes']
                + $totals['no_habilitados'] + $totals['sin_clasificar']
        );
    }

    private function callPrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    public function test_docente_descarga_el_excel_con_las_mismas_cifras_del_reporte(): void
    {
        $exam = $this->finishedExam(['nombre_examen' => 'Parcial final']);

        $response = $this->get($this->url($exam) . '/excel')->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'reporte-final-parcial-final-' . $exam->fecha->toDateString() . '.xlsx',
            $response->headers->get('Content-Disposition')
        );

        $spreadsheet = $this->loadSpreadsheet($response->getContent());

        $this->assertSame(['Resumen', 'Estudiantes', 'Incidencias'], $spreadsheet->getSheetNames());

        $rows = $spreadsheet->getSheetByName('Resumen')->toArray();
        $total = collect($rows)->first(fn ($row) => $row[0] === 'Total');
        $this->assertEquals(['Total', 2, 1, 0, 1], array_slice($total, 0, 5));

        $this->assertSame(3, $spreadsheet->getSheetByName('Estudiantes')->getHighestRow());
        $this->assertSame(
            'No se registraron incidencias en este examen',
            $spreadsheet->getSheetByName('Incidencias')->getCell('A2')->getValue()
        );
    }

    public function test_con_un_no_habilitado_el_reporte_y_el_excel_muestran_su_total_aparte(): void
    {
        $exam = $this->finishedExam();
        $notEnabled = $this->groupStudentIds()[1];
        DB::table('examen_estudiante')
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $notEnabled)
            ->update(['estado_habilitacion' => 'NO_HABILITADO', 'observacion' => 'NO_FIGURA_EN_LISTA']);

        $totals = $this->getJson($this->url($exam))->assertOk()->json('data.asistencia.totales');

        $this->assertSame(0, $totals['ausentes']);
        $this->assertSame(1, $totals['no_habilitados']);
        $this->assertSame(
            $totals['inscritos'],
            $totals['a_tiempo'] + $totals['con_retraso'] + $totals['ausentes']
                + $totals['no_habilitados'] + $totals['sin_clasificar']
        );

        $spreadsheet = $this->loadSpreadsheet($this->get($this->url($exam) . '/excel')->assertOk()->getContent());
        $rows = $spreadsheet->getSheetByName('Resumen')->toArray();
        $header = collect($rows)->first(fn ($row) => $row[0] === 'Grupo');
        $total = collect($rows)->first(fn ($row) => $row[0] === 'Total');

        $this->assertEquals(
            ['Grupo', 'Inscritos', 'A tiempo', 'Con retraso', 'Ausentes', 'No habilitados'],
            array_slice($header, 0, 6)
        );
        $this->assertEquals(['Total', 2, 1, 0, 0, 1], array_slice($total, 0, 6));
        $this->get($this->url($exam) . '/pdf')->assertOk();
    }

    public function test_sin_no_habilitados_el_excel_no_agrega_la_columna(): void
    {
        $exam = $this->finishedExam();

        $rows = $this->loadSpreadsheet($this->get($this->url($exam) . '/excel')->assertOk()->getContent())
            ->getSheetByName('Resumen')->toArray();
        $header = collect($rows)->first(fn ($row) => $row[0] === 'Grupo');

        $this->assertNotContains('No habilitados', $header);
    }

    public function test_un_texto_que_empieza_con_igual_no_se_guarda_como_formula_en_el_excel(): void
    {
        $exam = $this->finishedExam();
        $studentId = $this->groupStudentIds()[0];
        DB::table('estudiante')->where('id_estudiante', $studentId)->update(['nombre' => '=1+1']);

        $response = $this->get($this->url($exam) . '/excel')->assertOk();

        $sheet = $this->loadSpreadsheet($response->getContent())->getSheetByName('Estudiantes');
        $found = false;

        foreach ([2, 3] as $row) {
            $cell = $sheet->getCell('B' . $row);

            if (str_starts_with((string) $cell->getValue(), '=1+1')) {
                $found = true;
                $this->assertFalse($cell->isFormula());
            }
        }

        $this->assertTrue($found);
    }

    public function test_no_se_descarga_el_excel_de_un_examen_en_curso(): void
    {
        $exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId]);

        $this->getJson($this->url($exam) . '/excel')->assertForbidden();
    }

    private function loadSpreadsheet(string $content): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'reporte');
        file_put_contents($path, $content);

        try {
            return IOFactory::createReader('Xlsx')->load($path);
        } finally {
            @unlink($path);
        }
    }
}