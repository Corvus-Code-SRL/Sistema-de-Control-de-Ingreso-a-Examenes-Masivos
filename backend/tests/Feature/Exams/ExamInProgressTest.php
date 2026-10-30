<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamInProgressTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();
    }

    private function url(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}/en-curso";
    }

    private function examWithRoster(string $state = Exam::EN_CURSO): Exam
    {
        $exam = $this->createExam(
            ['estado' => $state],
            [$this->aulaId]
        );

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        return $exam;
    }

    public function test_docente_consulta_nomina_y_totales(): void
    {
        $exam = $this->examWithRoster();

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.estado', Exam::EN_CURSO)
            ->assertJsonPath('data.rol_controlador', 'DOCENTE')
            ->assertJsonPath('data.totales.total', 2)
            ->assertJsonPath('data.totales.presentes', 0)
            ->assertJsonPath('data.totales.ausentes', 2)
            ->assertJsonCount(2, 'data.estudiantes');
    }

    public function test_docente_ajeno_no_consulta_el_examen(): void
    {
        $exam = $this->examWithRoster();

        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson($this->url($exam))
            ->assertForbidden();
    }

    public function test_rechaza_estados_no_permitidos(): void
    {
        foreach ([
            Exam::PROGRAMADO,
            Exam::EN_INGRESO,
            Exam::CANCELADO,
        ] as $state) {
            $exam = $this->createExam(['estado' => $state]);

            $this->getJson($this->url($exam))
                ->assertStatus(409);
        }
    }

    public function test_permite_consultar_un_examen_finalizado(): void
    {
        $exam = $this->examWithRoster(Exam::FINALIZADO);

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.estado', Exam::FINALIZADO);
    }

    /**
     * Prepara dos ambientes con capacidad para un estudiante cada uno.
     * Así podemos comprobar que la nómina no se filtra solo visualmente.
     */
    private function examWithTwoRooms(): Exam
    {
        $exam = $this->createExam(
            ['estado' => Exam::EN_CURSO],
            [$this->aulaId, $this->otraAulaId]
        );

        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        DB::table('ambiente')
            ->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])
            ->update(['capacidad' => 1]);

        return $exam;
    }

    /**
     * Crea un auxiliar de prueba y lo autentica.
     * Puede estar habilitado o no para el examen.
     */
    private function actAsExamAssistant(
        Exam $exam,
        ?int $roomId,
        bool $enabled = true
    ): void {
        $auxiliaryId = '33333333-3333-4333-8333-333333333333';

        DB::table('usuario')->insert([
            'id_usuario' => $auxiliaryId,
            'nombre' => 'Auxiliar',
            'apellido_paterno' => 'Prueba',
            'correo' => 'auxiliar.en.curso@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '202401999',
            'estado' => 'ACTIVO',
        ]);

        $roleId = Role::query()
            ->where('nombre_rol', Role::AUXILIAR)
            ->firstOrFail()
            ->id_rol;

        DB::table('usuario_rol')->insert([
            'id_usuario' => $auxiliaryId,
            'id_rol' => $roleId,
            'fecha_inicio' => now(),
        ]);

        if ($enabled) {
            DB::table('examen_auxiliar')->insert([
                'id_examen' => $exam->id_examen,
                'id_usuario' => $auxiliaryId,
                'id_usuario_docente_habilita' => $this->docenteId,
                'id_ambiente' => $roomId,
            ]);
        }

        $this->actAsUserId($auxiliaryId);
    }

    public function test_auxiliar_ve_solo_estudiantes_de_su_ambiente(): void
    {
        $exam = $this->examWithTwoRooms();

        $studentIds = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->all();

        $this->actAsExamAssistant($exam, $this->aulaId);

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'AUXILIAR')
            ->assertJsonPath('data.id_ambiente_asignado', $this->aulaId)
            ->assertJsonPath('data.totales.total', 1)
            ->assertJsonPath('data.totales.ausentes', 1)
            ->assertJsonCount(1, 'data.estudiantes')
            ->assertJsonPath(
                'data.estudiantes.0.id_estudiante',
                (int) $studentIds[0]
            );
    }

    public function test_auxiliar_del_segundo_ambiente_ve_su_propia_nomina(): void
    {
        $exam = $this->examWithTwoRooms();

        $studentIds = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->all();

        $this->actAsExamAssistant($exam, $this->otraAulaId);

        $this->getJson($this->url($exam))
            ->assertOk()
            ->assertJsonPath(
                'data.id_ambiente_asignado',
                $this->otraAulaId
            )
            ->assertJsonCount(1, 'data.estudiantes')
            ->assertJsonPath(
                'data.estudiantes.0.id_estudiante',
                (int) $studentIds[1]
            );
    }

    public function test_auxiliar_no_habilitado_no_accede_al_examen(): void
    {
        $exam = $this->examWithTwoRooms();

        $this->actAsExamAssistant($exam, null, false);

        $this->getJson($this->url($exam))
            ->assertForbidden();
    }

    public function test_auxiliar_sin_ambiente_en_examen_multiaula_no_accede(): void
    {
        $exam = $this->examWithTwoRooms();

        $this->actAsExamAssistant($exam, null);

        $this->getJson($this->url($exam))
            ->assertForbidden();
    }

    public function test_respuesta_incluye_hora_del_servidor_en_utc(): void
    {
        $exam = $this->examWithRoster();

        $before = now('UTC')->subSeconds(2);

        $response = $this->getJson($this->url($exam));

        $after = now('UTC')->addSeconds(2);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['hora_servidor_en'],
            ]);

        $raw = $response->json('data.hora_servidor_en');

        $this->assertIsString($raw);

        $serverTime = \Carbon\Carbon::parse($raw);

        // La referencia horaria debe estar expresada en UTC.
        $this->assertSame(0, $serverTime->getOffset());

        // Debe corresponder al momento real de la consulta.
        $this->assertTrue(
            $serverTime->between($before, $after)
        );
    }

    public function test_docente_ajeno_no_conoce_estado_de_examen_programado(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::PROGRAMADO,
        ]);

        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson($this->url($exam))
            ->assertForbidden();
    }

    public function test_docente_ajeno_no_conoce_estado_de_examen_finalizado(): void
    {
        $exam = $this->createExam([
            'estado' => Exam::FINALIZADO,
        ]);

        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson($this->url($exam))
            ->assertForbidden();
    }
}
