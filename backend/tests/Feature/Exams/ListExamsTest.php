<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * GET /api/examenes — vista Programados: los exámenes del docente actual.
 */
class ListExamsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_lista_solo_los_examenes_del_docente_actual_ordenados_por_fecha(): void
    {
        $lejano = $this->createExam(['nombre_examen' => 'Examen lejano', 'fecha' => $this->futureDate(20)]);
        $cercano = $this->createExam(['nombre_examen' => 'Examen cercano', 'fecha' => $this->futureDate(5)]);
        $this->createExam([
            'nombre_examen'      => 'Examen ajeno',
            'id_usuario_docente' => $this->otroDocenteId,
            'fecha'              => $this->futureDate(1),
        ]);

        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id_examen', $cercano->id_examen)
            ->assertJsonPath('data.1.id_examen', $lejano->id_examen);
    }

    public function test_expone_el_estado_de_cada_examen(): void
    {
        $this->createExam(['estado' => Exam::EN_INGRESO]);

        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.estado', Exam::EN_INGRESO);
    }

    public function test_una_lista_vacia_responde_data_vacio(): void
    {
        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
    public function test_el_listado_trae_ambientes_y_grupos_con_su_cantidad_de_estudiantes(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        $this->linkGroup($exam, $this->grupoPropioId);

        $this->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.ambientes.0.nro_aula', '691A')
            ->assertJsonPath('data.0.grupos.0.id_grupo', $this->grupoPropioId)
            ->assertJsonPath('data.0.grupos.0.cantidad_estudiantes', 2);
    }

    /* ------------------------------ ?vista=programados (HU-024) ------------------------------ */

    public function test_la_vista_programados_solo_trae_programado_y_en_ingreso(): void
    {
        $programado = $this->linkedExam(['nombre_examen' => 'Programado', 'estado' => Exam::PROGRAMADO, 'fecha' => $this->futureDate(3)]);
        $enIngreso = $this->linkedExam(['nombre_examen' => 'En ingreso', 'estado' => Exam::EN_INGRESO, 'fecha' => $this->futureDate(1)]);
        $this->linkedExam(['nombre_examen' => 'En curso', 'estado' => Exam::EN_CURSO]);
        $this->linkedExam(['nombre_examen' => 'Finalizado', 'estado' => Exam::FINALIZADO]);
        $this->linkedExam(['nombre_examen' => 'Cancelado', 'estado' => Exam::CANCELADO]);

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id_examen', $enIngreso->id_examen)
            ->assertJsonPath('data.1.id_examen', $programado->id_examen);
    }

    public function test_la_vista_programados_ordena_por_fecha_y_hora_ascendente(): void
    {
        $tarde = $this->linkedExam(['nombre_examen' => 'Tarde', 'fecha' => $this->futureDate(4), 'hora_inicio' => '14:00']);
        $lejano = $this->linkedExam(['nombre_examen' => 'Lejano', 'fecha' => $this->futureDate(30)]);
        $temprano = $this->linkedExam(['nombre_examen' => 'Temprano', 'fecha' => $this->futureDate(4), 'hora_inicio' => '08:00']);

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonPath('data.0.id_examen', $temprano->id_examen)
            ->assertJsonPath('data.1.id_examen', $tarde->id_examen)
            ->assertJsonPath('data.2.id_examen', $lejano->id_examen);
    }

    public function test_la_vista_programados_excluye_los_examenes_de_otro_periodo(): void
    {
        $this->linkedExam(['nombre_examen' => 'Periodo anterior'], $this->grupoPeriodoAnteriorId);
        $vigente = $this->linkedExam(['nombre_examen' => 'Periodo activo']);

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $vigente->id_examen);
    }

    public function test_la_vista_programados_incluye_un_examen_con_grupos_de_ambos_periodos(): void
    {
        $mixto = $this->linkedExam([], $this->grupoPeriodoAnteriorId);
        $this->linkGroup($mixto, $this->grupoPropioId);

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $mixto->id_examen);
    }

    public function test_un_examen_recien_creado_sin_grupos_aparece_en_la_vista_programados(): void
    {
        $sinGrupos = $this->createExam(['nombre_examen' => 'Recién creado']);

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_examen', $sinGrupos->id_examen)
            ->assertJsonPath('data.0.estado', Exam::PROGRAMADO);
    }

    public function test_un_examen_creado_por_la_api_aparece_en_la_vista_programados(): void
    {
        $id = $this->postJson('/api/examenes', $this->validPayload(['confirmar_advertencias' => true]))
            ->assertCreated()
            ->json('data.id_examen');

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonPath('data.0.id_examen', $id);
    }

    public function test_la_vista_programados_no_trae_examenes_de_otro_docente(): void
    {
        $this->createExam(['nombre_examen' => 'Ajeno', 'id_usuario_docente' => $this->otroDocenteId]);

        $this->getJson('/api/examenes?vista=programados')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_rechaza_con_422_una_vista_desconocida(): void
    {
        $this->getJson('/api/examenes?vista=todos')
            ->assertStatus(422)
            ->assertJsonValidationErrors('vista');
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/examenes?vista=programados')->assertUnauthorized();
    }

    /** Examen del docente vinculado a un grupo (el del periodo activo si no se indica otro). */
    private function linkedExam(array $overrides = [], ?int $groupId = null): Exam
    {
        $exam = $this->createExam($overrides);
        $this->linkGroup($exam, $groupId ?? $this->grupoPropioId);

        return $exam;
    }

    private function linkGroup(Exam $exam, int $groupId): void
    {
        DB::table('grupo_examen')->insert(['id_grupo' => $groupId, 'id_examen' => $exam->id_examen]);
    }
}
