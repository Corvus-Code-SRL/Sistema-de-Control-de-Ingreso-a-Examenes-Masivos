<?php

namespace Tests\Feature\Exams;

use App\Services\Exams\Importers\EligibleListPreviewResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRosterFiles;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * La plantilla que se descarga en la vista (la misma de la nómina, HU-021) debe pasar el
 * parser y el preview de HU-028 tal cual. El emparejamiento es solo por código SIS.
 */
class EligibleListTemplateTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use BuildsRosterFiles;

    private const TEMPLATE = '/../frontend/public/plantillas/nomina-plantilla.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
        $this->actAsTeacher($this->docenteId);
    }

    public function test_la_plantilla_sin_cambios_pasa_el_parser_y_el_preview(): void
    {
        $path = base_path() . self::TEMPLATE;
        $this->assertFileExists($path);

        $exam = $this->createExam();
        $this->post(
            "/api/examenes/{$exam->id_examen}/habilitados/preview",
            ['archivo' => new UploadedFile($path, 'nomina-plantilla.csv', null, null, true)],
            ['Accept' => 'application/json']
        )
            ->assertOk()
            ->assertJsonPath('data.filas_inconsistentes', 0)
            ->assertJsonPath('data.filas_validas', 2)
            ->assertJsonPath('data.filas.0.codigo_sis', '202400001')
            ->assertJsonPath('data.filas.0.estado', EligibleListPreviewResult::NOT_FOUND_IN_EXAM)
            ->assertJsonPath('data.filas.1.codigo_sis', '202400002');
    }

    public function test_se_compara_solo_por_codigo_sis_y_los_demas_datos_son_referenciales(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        $sis = (string) DB::table('estudiante')
            ->join('grupo_estudiante', 'grupo_estudiante.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('grupo_estudiante.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->value('estudiante.cod_sis');

        // La plantilla con el código SIS de un estudiante real y apellidos y nombres que no coinciden.
        $content = str_replace('202400001', $sis, (string) file_get_contents(base_path() . self::TEMPLATE));
        $file = $this->rosterFile('plantilla-llena.csv', $content);

        $this->post(
            "/api/examenes/{$exam->id_examen}/habilitados/preview",
            ['archivo' => $file],
            ['Accept' => 'application/json']
        )
            ->assertOk()
            ->assertJsonPath('data.filas_inconsistentes', 0)
            ->assertJsonPath('data.filas.0.estado', EligibleListPreviewResult::MATCHED)
            ->assertJsonPath('data.faltantes', 1);
    }
}
