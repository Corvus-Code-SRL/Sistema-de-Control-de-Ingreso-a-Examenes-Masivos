<?php

namespace Tests\Feature\Academic;

use App\Support\RecordStatus;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

/**
 * HU-021 (multicarrera): una nómina con estudiantes de varias carreras.
 *
 * La carrera de cada fila sale de la columna opcional «carrera», de la carrera elegida para todo
 * el archivo o, si el grupo atiende una sola, de esa. El grupo propio es de Sistemas; en las
 * pruebas que lo necesitan también atiende a Informática.
 */
class StudentRosterMultipleCareersTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CacheRepository::class, new Repository(new ArrayStore()));
        $this->seedAcademicCatalog();
    }

    public function test_una_nomina_mixta_inscribe_a_cada_estudiante_con_su_carrera(): void
    {
        $this->addInformatica();

        $preview = $this->preview(implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres,Carrera',
            '202400001,ROJAS VACA,ANA,SIS',
            '202400002,PAZ LUNA,LUIS,Ingeniería Informática',
            '202400003,MAMANI CRUZ,EVA,ingenieria de sistemas',
        ]))->assertOk();

        $preview->assertJsonPath('data.filas_validas', 3);
        $preview->assertJsonPath('data.filas.0.carrera.id_carrera', $this->sistemasId);
        $preview->assertJsonPath('data.filas.1.carrera.id_carrera', $this->informaticaId);
        $preview->assertJsonPath('data.filas.1.carrera_archivo', 'Ingeniería Informática');
        $preview->assertJsonCount(2, 'data.carreras_grupo');

        $this->confirm($preview->json('data.token'))->assertOk();

        $this->assertSame($this->sistemasId, $this->careerOf('202400001'));
        $this->assertSame($this->informaticaId, $this->careerOf('202400002'));
        $this->assertSame($this->sistemasId, $this->careerOf('202400003'));
    }

    public function test_marca_la_fila_con_una_carrera_que_el_grupo_no_atiende(): void
    {
        $preview = $this->preview(implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres,Carrera',
            '202400001,ROJAS VACA,ANA,SIS',
            '202400002,PAZ LUNA,LUIS,INF',
        ]))->assertOk();

        $preview->assertJsonPath('data.filas_inconsistentes', 1);
        $preview->assertJsonPath('data.filas.1.estado', 'inconsistent');
        $preview->assertJsonPath('data.filas.1.errores', ['career_not_in_group']);
        $preview->assertJsonPath('data.filas.1.carrera', null);
    }

    public function test_sin_columna_en_un_grupo_de_varias_carreras_la_fila_queda_sin_carrera(): void
    {
        $this->addInformatica();

        $preview = $this->preview(implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '202400001,ROJAS VACA,ANA',
        ]))->assertOk();

        $preview->assertJsonPath('data.filas.0.estado', 'inconsistent');
        $preview->assertJsonPath('data.filas.0.errores', ['missing_career']);
    }

    public function test_la_carrera_elegida_completa_las_filas_sin_columna(): void
    {
        $this->addInformatica();

        $preview = $this->preview(implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres,Carrera',
            '202400001,ROJAS VACA,ANA,',
            '202400002,PAZ LUNA,LUIS,SIS',
        ]), $this->informaticaId)->assertOk();

        $preview->assertJsonPath('data.filas_validas', 2);
        $preview->assertJsonPath('data.filas.0.carrera.id_carrera', $this->informaticaId);
        $preview->assertJsonPath('data.filas.1.carrera.id_carrera', $this->sistemasId);

        $this->confirm($preview->json('data.token'))->assertOk();

        $this->assertSame($this->informaticaId, $this->careerOf('202400001'));
        $this->assertSame($this->sistemasId, $this->careerOf('202400002'));
    }

    public function test_rechaza_una_carrera_elegida_que_no_es_del_grupo(): void
    {
        $this->preview(implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '202400001,ROJAS VACA,ANA',
        ]), $this->informaticaId)
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_carrera');
    }

    public function test_en_un_grupo_de_una_sola_carrera_la_columna_no_hace_falta(): void
    {
        $preview = $this->preview(implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres',
            '202400001,ROJAS VACA,ANA',
        ]))->assertOk();

        $preview->assertJsonPath('data.filas.0.carrera.id_carrera', $this->sistemasId);

        $this->confirm($preview->json('data.token'))->assertOk();

        $this->assertSame($this->sistemasId, $this->careerOf('202400001'));
    }

    public function test_si_el_grupo_deja_de_atender_la_carrera_tras_el_preview_esa_fila_no_se_importa(): void
    {
        $this->addInformatica();

        $preview = $this->preview(implode(PHP_EOL, [
            'Estudiante,Apellidos,Nombres,Carrera',
            '202400001,ROJAS VACA,ANA,SIS',
            '202400002,PAZ LUNA,LUIS,INF',
        ]))->assertOk();

        DB::table('grupo_oferta')
            ->where('id_grupo', $this->grupoPropioId)
            ->where('id_carrera', $this->informaticaId)
            ->delete();

        $this->confirm($preview->json('data.token'))
            ->assertOk()
            ->assertJsonPath('data.filas_inconsistentes', 1)
            ->assertJsonPath('data.estudiantes_inscritos', 1);

        $this->assertSame($this->sistemasId, $this->careerOf('202400001'));
        $this->assertFalse(DB::table('estudiante')->where('cod_sis', '202400002')->exists());
    }

    public function test_lee_la_columna_carrera_de_un_xlsx(): void
    {
        $this->addInformatica();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Estudiante', 'Apellidos', 'Nombres', 'Carrera'],
            ['202400001', 'ROJAS VACA', 'ANA', 'INF'],
        ]);
        $sheet->getCell('A2')->setValueExplicit('202400001', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $path = tempnam(sys_get_temp_dir(), 'sciem_xlsx_');
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->post(
            '/api/grupos/' . $this->grupoPropioId . '/nomina/preview',
            ['archivo' => new UploadedFile($path, 'nomina.xlsx', null, null, true)],
            ['Accept' => 'application/json']
        );

        @unlink($path);

        $response->assertOk()->assertJsonPath('data.filas.0.carrera.id_carrera', $this->informaticaId);
    }

    public function test_la_lista_de_estudiantes_se_filtra_por_carrera(): void
    {
        $this->addInformatica();
        $this->enroll('202400001', $this->sistemasId);
        $this->enroll('202400002', $this->informaticaId);
        $this->enroll('202400003', $this->informaticaId);

        $url = '/api/grupos/' . $this->grupoPropioId . '/estudiantes';

        $all = $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total_inscritos', 3);

        $totals = array_column($all->json('meta.por_carrera'), 'total', 'id_carrera');
        $this->assertSame([$this->sistemasId => 1, $this->informaticaId => 2], [
            $this->sistemasId => $totals[$this->sistemasId] ?? null,
            $this->informaticaId => $totals[$this->informaticaId] ?? null,
        ]);

        $filtered = $this->getJson($url . '?id_carrera=' . $this->informaticaId)->assertOk();

        $filtered->assertJsonCount(2, 'data');
        $filtered->assertJsonPath('data.0.carrera.id_carrera', $this->informaticaId);
        $filtered->assertJsonPath('meta.total_inscritos', 3);
    }

    public function test_rechaza_un_filtro_de_carrera_no_numerico(): void
    {
        $this->getJson('/api/grupos/' . $this->grupoPropioId . '/estudiantes?id_carrera=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_carrera');
    }

    private function addInformatica(): void
    {
        $group = DB::table('grupo')->where('id_grupo', $this->grupoPropioId)->first();

        foreach ([$this->sistemasId, $this->informaticaId] as $careerId) {
            DB::table('grupo_oferta')->insertOrIgnore([
                'id_grupo' => $this->grupoPropioId,
                'id_carrera' => $careerId,
                'id_materia' => (int) $group->id_materia,
                'id_gestion_periodo' => (int) $group->id_gestion_periodo,
            ]);
        }
    }

    private function enroll(string $sisCode, int $careerId): void
    {
        $studentId = DB::table('estudiante')->insertGetId([
            'cod_sis' => $sisCode,
            'nombre' => 'NOMBRE' . $sisCode,
            'apellido_paterno' => 'APELLIDO' . $sisCode,
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
            'id_carrera' => $careerId,
        ]);
    }

    private function careerOf(string $sisCode): ?int
    {
        $careerId = DB::table('grupo_estudiante')
            ->join('estudiante', 'estudiante.id_estudiante', '=', 'grupo_estudiante.id_estudiante')
            ->where('grupo_estudiante.id_grupo', $this->grupoPropioId)
            ->where('estudiante.cod_sis', $sisCode)
            ->value('grupo_estudiante.id_carrera');

        return $careerId === null ? null : (int) $careerId;
    }

    private function preview(string $csv, ?int $careerId = null)
    {
        $data = ['archivo' => UploadedFile::fake()->createWithContent('nomina.csv', $csv)];

        if ($careerId !== null) {
            $data['id_carrera'] = $careerId;
        }

        return $this->post(
            '/api/grupos/' . $this->grupoPropioId . '/nomina/preview',
            $data,
            ['Accept' => 'application/json']
        );
    }

    private function confirm(?string $token)
    {
        return $this->postJson(
            '/api/grupos/' . $this->grupoPropioId . '/nomina/confirm',
            ['token' => $token]
        );
    }
}
