<?php

namespace Tests\Feature\Seeders;

use App\Support\ReportStatus;
use Database\Seeders\ActionSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExamTypeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\TestData\CatalogTestDataSeeder;
use Database\Seeders\TestData\ExamTestDataSeeder;
use Database\Seeders\TestData\GroupTestDataSeeder;
use Database\Seeders\TestData\RiskCenterTestDataSeeder;
use Database\Seeders\TestData\TestDataIds as Ids;
use Database\Seeders\TestData\TestDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

/**
 * Datos de prueba de la central de riesgo: intentos de ingreso, reportes de estudiantes y su central.
 * TestDataSeeder rechaza sciem_test, así que se siembran los sub-seeders, como en TestDataPeriodSeedingTest.
 */
class RiskCenterTestDataSeedingTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;

    private const EVIDENCE = [
        9921 => 'png',
        9923 => 'pdf',
        9925 => 'png',
        9926 => 'png',
        9928 => 'pdf',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function seedBase(): void
    {
        $this->seed([
            RoleSeeder::class,
            ActionSeeder::class,
            ExamTypeSeeder::class,
            CatalogTestDataSeeder::class,
            AccountTestDataSeeder::class,
            GroupTestDataSeeder::class,
            ExamTestDataSeeder::class,
        ]);
    }

    private function seedRiskCenter(): void
    {
        $this->seedBase();
        $this->seed(RiskCenterTestDataSeeder::class);
    }

    private function reports(): \Illuminate\Support\Collection
    {
        return DB::table('reporte_estudiante')
            ->whereBetween('id_reporte_est', [Ids::STUDENT_REPORT_FIRST, Ids::STUDENT_REPORT_LAST])
            ->orderBy('id_reporte_est')
            ->get()
            ->keyBy('id_reporte_est');
    }

    private function attempts(): \Illuminate\Support\Collection
    {
        return DB::table('intento_ingreso')
            ->whereBetween('id_intento', [Ids::ENTRY_ATTEMPT_FIRST, Ids::ENTRY_ATTEMPT_LAST])
            ->orderBy('id_intento')
            ->get()
            ->keyBy('id_intento');
    }

    private function riskCenter(): \Illuminate\Support\Collection
    {
        return DB::table('central_riesgo')
            ->whereBetween('id_cr_estudiante', [Ids::RISK_CENTER_FIRST, Ids::RISK_CENTER_LAST])
            ->orderBy('id_cr_estudiante')
            ->get();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function snapshot(): array
    {
        $tables = [
            'estudiante' => 'id_estudiante',
            'grupo_estudiante' => 'id_grupo, id_estudiante',
            'examen_auxiliar' => 'id_examen, id_usuario',
            'intento_ingreso' => 'id_intento',
            'reporte_estudiante' => 'id_reporte_est',
            'central_riesgo' => 'id_cr_estudiante',
        ];

        $snapshot = [];

        foreach ($tables as $table => $order) {
            $snapshot[$table] = DB::table($table)->orderByRaw($order)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    public function test_siembra_once_intentos_nueve_reportes_y_cinco_filas_de_la_central(): void
    {
        $this->seedRiskCenter();

        $this->assertCount(11, $this->attempts());
        $this->assertCount(9, $this->reports());
        $this->assertCount(5, $this->riskCenter());
    }

    public function test_los_intentos_cubren_reportados_sin_reporte_y_no_encontrado(): void
    {
        $this->seedRiskCenter();

        $linked = $this->reports()->pluck('id_intento')->all();
        $withoutReport = $this->attempts()->reject(fn ($attempt) => in_array($attempt->id_intento, $linked, true));

        $this->assertCount(9, $linked);
        $this->assertCount(9, array_unique($linked));
        $this->assertSame([9910, 9911], $withoutReport->keys()->all());

        $identified = $withoutReport[9910];
        $this->assertNotNull($identified->id_estudiante);

        $notFound = $withoutReport[9911];
        $this->assertSame('NO_ENCONTRADO', $notFound->motivo);
        $this->assertNull($notFound->id_estudiante);
        $this->assertSame('202199999', $notFound->cod_sis);
        $this->assertFalse(DB::table('estudiante')->where('cod_sis', '202199999')->exists());
    }

    public function test_los_reportes_cubren_los_tres_estados_con_y_sin_evidencia(): void
    {
        $this->seedRiskCenter();
        $reports = $this->reports();

        $this->assertSame(
            [ReportStatus::APPROVED => 5, ReportStatus::PENDING => 2, ReportStatus::REJECTED => 2],
            $reports->countBy('estado')->sortKeys()->all()
        );

        foreach ([ReportStatus::PENDING, ReportStatus::REJECTED] as $status) {
            $group = $reports->where('estado', $status);
            $this->assertCount(1, $group->filter(fn ($report) => $report->evidencia_url !== null), $status);
            $this->assertCount(1, $group->filter(fn ($report) => $report->evidencia_url === null), $status);
        }

        $approved = $reports->where('estado', ReportStatus::APPROVED);
        $this->assertGreaterThan(0, $approved->filter(fn ($report) => $report->evidencia_url !== null)->count());
        $this->assertGreaterThan(0, $approved->filter(fn ($report) => $report->evidencia_url === null)->count());

        foreach ($reports as $id => $report) {
            $this->assertSame($report->evidencia_url === null, $report->evidencia_public_id === null, (string) $id);
            $this->assertSame(isset(self::EVIDENCE[$id]), $report->evidencia_url !== null, (string) $id);

            if ($report->estado === ReportStatus::PENDING) {
                $this->assertNull($report->fecha_revision);
                $this->assertNull($report->id_usuario_revisor);
            } else {
                $this->assertNotNull($report->fecha_revision);
                $this->assertSame(Ids::TEACHER_FIXED, $report->id_usuario_revisor);
            }
        }
    }

    public function test_solo_los_reportes_aprobados_tienen_fila_en_la_central(): void
    {
        $this->seedRiskCenter();
        $reports = $this->reports();

        $this->assertSame(
            $reports->where('estado', ReportStatus::APPROVED)->keys()->sort()->values()->all(),
            $this->riskCenter()->pluck('id_reporte_est')->sort()->values()->all()
        );

        foreach ($this->riskCenter() as $row) {
            $report = $reports[$row->id_reporte_est];

            $this->assertSame($report->id_estudiante, $row->id_estudiante);
            $this->assertSame(
                (new \DateTime($report->fecha_revision))->getTimestamp(),
                (new \DateTime($row->fecha_registro))->getTimestamp()
            );
        }

        $this->assertCount(5, $this->riskCenter()->pluck('fecha_registro')->unique());
    }

    public function test_la_central_tiene_tres_estudiantes_y_los_cuatro_tipos_de_falta(): void
    {
        $this->seedRiskCenter();

        $rows = DB::table('central_riesgo as cr')
            ->join('reporte_estudiante as r', 'r.id_reporte_est', '=', 'cr.id_reporte_est')
            ->join('tipo_falta as t', 't.id_falta', '=', 'r.id_tipo_falta')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'cr.id_estudiante')
            ->whereBetween('cr.id_cr_estudiante', [Ids::RISK_CENTER_FIRST, Ids::RISK_CENTER_LAST])
            ->orderBy('t.nombre')
            ->get(['e.nombre', 'e.apellido_paterno', 't.nombre as falta']);

        $byStudent = $rows->groupBy(fn ($row) => $row->nombre . ' ' . $row->apellido_paterno)
            ->map(fn ($group) => $group->pluck('falta')->sort()->values()->all())
            ->sortKeys()
            ->all();

        $this->assertSame([
            'Bruno Balderrama' => ['Copia en examen'],
            'Camila Camacho' => ['Conducta indebida', 'Suplantación de identidad'],
            'José Pérez' => ['Copia en examen', 'Material no permitido'],
        ], $byStudent);
        $this->assertCount(4, $rows->pluck('falta')->unique());
    }

    public function test_cada_reporte_es_coherente_con_su_intento_su_examen_y_sus_actores(): void
    {
        $this->seedRiskCenter();
        $attempts = $this->attempts();

        foreach ($this->reports() as $id => $report) {
            $attempt = $attempts[$report->id_intento];

            $this->assertSame($attempt->id_examen, $report->id_examen, "examen del reporte {$id}");
            $this->assertSame($attempt->id_estudiante, $report->id_estudiante, "estudiante del reporte {$id}");
            $this->assertSame(
                $attempt->id_usuario_controlador,
                $report->id_usuario_reportante,
                "reportante del reporte {$id}"
            );
            $this->assertGreaterThan(
                (new \DateTime($attempt->registrado_en))->getTimestamp(),
                (new \DateTime($report->fecha_reporte))->getTimestamp(),
                "fecha del reporte {$id}"
            );

            // El estudiante cursa un grupo del examen.
            $this->assertTrue(
                DB::table('grupo_examen as ge')
                    ->join('grupo_estudiante as gs', 'gs.id_grupo', '=', 'ge.id_grupo')
                    ->where('ge.id_examen', $report->id_examen)
                    ->where('gs.id_estudiante', $report->id_estudiante)
                    ->where('gs.estado', 'ACTIVO')
                    ->exists(),
                "el estudiante del reporte {$id} no pertenece al examen"
            );

            // Quien controla el ingreso es un auxiliar habilitado en el examen y en el ambiente del intento.
            $this->assertTrue(
                DB::table('examen_auxiliar')
                    ->where('id_examen', $attempt->id_examen)
                    ->where('id_usuario', $attempt->id_usuario_controlador)
                    ->where('id_ambiente', $attempt->id_ambiente)
                    ->exists(),
                "el controlador del intento {$attempt->id_intento} no está habilitado"
            );

            // El revisor es el docente del examen, nunca quien reportó.
            if ($report->id_usuario_revisor !== null) {
                $this->assertSame(
                    $report->id_usuario_revisor,
                    DB::table('examen')->where('id_examen', $report->id_examen)->value('id_usuario_docente')
                );
                $this->assertNotSame($report->id_usuario_reportante, $report->id_usuario_revisor);
            }
        }
    }

    public function test_los_tipos_de_falta_se_resuelven_por_nombre(): void
    {
        DB::table('tipo_falta')->insert(['nombre' => 'Relleno', 'descripcion' => 'Desplaza los ids.']);
        DB::table('tipo_falta')->insert(['nombre' => 'Copia en examen', 'descripcion' => 'Descripción del equipo.']);

        $this->seedRiskCenter();

        $this->assertSame(1, DB::table('tipo_falta')->where('nombre', 'Copia en examen')->count());
        $this->assertSame(5, DB::table('tipo_falta')->count());
        $this->assertSame(
            DB::table('tipo_falta')->where('nombre', 'Copia en examen')->value('id_falta'),
            $this->reports()[9921]->id_tipo_falta
        );
    }

    public function test_dos_ejecuciones_dejan_snapshots_identicos(): void
    {
        $this->seedRiskCenter();
        $first = $this->snapshot();
        $files = Storage::disk('public')->allFiles();

        $this->seed(RiskCenterTestDataSeeder::class);

        $this->assertSame($first, $this->snapshot());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_al_resembrar_se_reconstruye_la_central_del_fixture_y_no_se_tocan_filas_ajenas(): void
    {
        $this->seedRiskCenter();
        $before = $this->snapshot();

        // Alguien aprobó a mano el pendiente 9926 y rechazó a mano el aprobado 9921.
        DB::table('reporte_estudiante')->where('id_reporte_est', 9926)->update([
            'estado' => ReportStatus::APPROVED,
            'fecha_revision' => '2026-09-30 10:00:00-04',
            'id_usuario_revisor' => Ids::TEACHER_FIXED,
        ]);
        DB::table('central_riesgo')->insert(
            ['id_cr_estudiante' => 9999, 'id_reporte_est' => 9926, 'id_estudiante' => 9501]
        );
        DB::table('central_riesgo')->where('id_cr_estudiante', 9941)->delete();

        // Y existe un reporte ajeno al fixture, ya aprobado en la central.
        $context = $this->createReportingContext();
        $foreignReport = $this->insertStudentReport($context, [
            'estado' => ReportStatus::APPROVED,
            'fecha_revision' => '2026-09-30 10:00:00-04',
            'id_usuario_revisor' => $context['id_usuario'],
        ]);
        DB::table('central_riesgo')->insert(
            ['id_reporte_est' => $foreignReport, 'id_estudiante' => $context['id_estudiante']]
        );

        $this->seed(RiskCenterTestDataSeeder::class);

        $this->assertSame(ReportStatus::PENDING, $this->reports()[9926]->estado);
        $this->assertFalse(DB::table('central_riesgo')->where('id_reporte_est', 9926)->exists());
        $this->assertTrue(DB::table('central_riesgo')->where('id_cr_estudiante', 9941)->exists());
        $this->assertTrue(DB::table('central_riesgo')->where('id_reporte_est', $foreignReport)->exists());

        $this->assertSame(
            $before['central_riesgo'],
            collect($this->snapshot()['central_riesgo'])->whereBetween('id_cr_estudiante', [9941, 9945])->values()->all()
        );
    }

    public function test_deja_archivos_minimos_solo_para_los_reportes_con_evidencia(): void
    {
        $this->seedRiskCenter();
        $disk = Storage::disk('public');

        $this->assertSame(
            ['evidencias/reportes/9921.png', 'evidencias/reportes/9923.pdf', 'evidencias/reportes/9925.png',
                'evidencias/reportes/9926.png', 'evidencias/reportes/9928.pdf'],
            collect($disk->allFiles())->sort()->values()->all()
        );

        foreach (self::EVIDENCE as $id => $extension) {
            $report = $this->reports()[$id];
            $path = "evidencias/reportes/{$id}.{$extension}";
            $content = $disk->get($path);

            $this->assertSame($path, $report->evidencia_public_id);
            $this->assertStringEndsWith($path, $report->evidencia_url);

            if ($extension === 'png') {
                $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $content);
                $this->assertLessThan(200, strlen($content));
            } else {
                $this->assertStringStartsWith('%PDF-', $content);
                $this->assertStringEndsWith("%%EOF\n", $content);
                $this->assertLessThan(1000, strlen($content));
            }
        }
    }

    public function test_el_pdf_de_prueba_tiene_una_tabla_xref_valida(): void
    {
        $this->seedRiskCenter();
        $pdf = Storage::disk('public')->get('evidencias/reportes/9923.pdf');

        preg_match('/startxref\n(\d+)\n/', $pdf, $startxref);
        $this->assertStringStartsWith('xref', substr($pdf, (int) $startxref[1], 4));

        preg_match_all('/^(\d{10}) 00000 n $/m', $pdf, $offsets);
        $this->assertCount(3, $offsets[1]);

        foreach ($offsets[1] as $index => $offset) {
            $this->assertStringStartsWith(($index + 1) . ' 0 obj', substr($pdf, (int) $offset, 7));
        }
    }

    public function test_nunca_pisa_un_archivo_existente(): void
    {
        $disk = Storage::disk('public');
        $disk->put('evidencias/reportes/9921.png', 'archivo real del equipo');
        $disk->put('evidencias/reportes/1.png', 'evidencia de un reporte de la aplicación');

        $this->seedRiskCenter();
        $this->seed(RiskCenterTestDataSeeder::class);

        $this->assertSame('archivo real del equipo', $disk->get('evidencias/reportes/9921.png'));
        $this->assertSame('evidencia de un reporte de la aplicación', $disk->get('evidencias/reportes/1.png'));
        $this->assertTrue($disk->exists('evidencias/reportes/9923.pdf'));
    }

    public function test_el_efecto_sobre_nominas_y_auxiliares_vive_solo_en_este_seeder(): void
    {
        $this->seedBase();

        $roster = fn (int $groupId) => DB::table('grupo_estudiante')->where('id_grupo', $groupId)->count();

        $this->assertSame(24, DB::table('estudiante')->where('id_estudiante', '>=', 9501)->count());
        $this->assertSame(6, $roster(Ids::GROUP_CALCULUS_SYS_1));
        $this->assertSame(3, $roster(Ids::GROUP_PROGRAMMING_SYS_2));
        $this->assertSame(0, DB::table('examen_auxiliar')->count());

        $this->seed(RiskCenterTestDataSeeder::class);

        $this->assertSame(25, DB::table('estudiante')->where('id_estudiante', '>=', 9501)->count());
        $this->assertSame(7, $roster(Ids::GROUP_CALCULUS_SYS_1));
        $this->assertSame(4, $roster(Ids::GROUP_PROGRAMMING_SYS_2));
        $this->assertSame(4, DB::table('examen_auxiliar')->count());

        // Las nóminas de los demás grupos no cambian.
        $this->assertSame(5, $roster(Ids::GROUP_CALCULUS_SYS_2));
        $this->assertSame(4, $roster(Ids::GROUP_PROGRAMMING_SYS_1));
    }

    public function test_jose_perez_cursa_los_grupos_de_los_dos_examenes(): void
    {
        $this->seedRiskCenter();

        $this->assertEqualsCanonicalizing(
            [Ids::GROUP_CALCULUS_SYS_1, Ids::GROUP_PROGRAMMING_SYS_2],
            DB::table('grupo_estudiante')->where('id_estudiante', Ids::STUDENT_JOSE_PEREZ)->pluck('id_grupo')->all()
        );
        $this->assertSame(
            '202150025',
            DB::table('estudiante')->where('id_estudiante', Ids::STUDENT_JOSE_PEREZ)->value('cod_sis')
        );
    }

    public function test_las_constantes_de_ids_son_unicas(): void
    {
        $constants = (new ReflectionClass(Ids::class))->getConstants();

        $this->assertNotEmpty($constants);
        $this->assertSame(
            [],
            array_keys(array_filter(array_count_values(array_map('strval', $constants)), fn ($count) => $count > 1))
        );
    }

    public function test_los_ids_del_fixture_estan_en_el_rango_de_prueba_y_sin_cruces(): void
    {
        $ranges = [
            [Ids::ENTRY_ATTEMPT_FIRST, Ids::ENTRY_ATTEMPT_LAST],
            [Ids::STUDENT_REPORT_FIRST, Ids::STUDENT_REPORT_LAST],
            [Ids::RISK_CENTER_FIRST, Ids::RISK_CENTER_LAST],
        ];

        foreach ($ranges as [$first, $last]) {
            $this->assertGreaterThanOrEqual(9000, $first);
            $this->assertLessThanOrEqual(9999, $last);
            $this->assertLessThan($last, $first);
        }

        for ($i = 1; $i < count($ranges); $i++) {
            $this->assertLessThan($ranges[$i][0], $ranges[$i - 1][1]);
        }
    }

    public function test_forma_parte_de_testdataseeder_y_no_de_databaseseeder(): void
    {
        $testData = file_get_contents((new ReflectionClass(TestDataSeeder::class))->getFileName());
        $general = file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());

        $this->assertStringContainsString('RiskCenterTestDataSeeder::class', $testData);
        $this->assertStringNotContainsString('RiskCenterTestDataSeeder', $general);
    }
}
