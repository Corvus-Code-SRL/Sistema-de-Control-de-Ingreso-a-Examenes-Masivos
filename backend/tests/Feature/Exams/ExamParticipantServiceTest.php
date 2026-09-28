<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Group;
use App\Services\Exams\ExamParticipantService;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Participantes de un examen: esperados = nómina de sus grupos, ingresados = filas de
 * examen_estudiante, pendientes = la diferencia. Nada se copia.
 */
class ExamParticipantServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        // El grupo propio queda con 2 estudiantes inscritos.
        $this->seedExamCatalog();

        $this->exam = $this->createExam();
        $this->link($this->exam, $this->grupoPropioId);
    }

    private function service(): ExamParticipantService
    {
        return app(ExamParticipantService::class);
    }

    private function link(Exam $exam, int $groupId): void
    {
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $groupId,
        ]);
    }

    private function enter(Exam $exam, int $studentId, int $groupId, string $state = 'INGRESO'): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $groupId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => $state,
            'hora_ingreso' => '08:05',
        ]);
    }

    private function rosterStudentIds(int $groupId): array
    {
        return DB::table('grupo_estudiante')
            ->where('id_grupo', $groupId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function test_los_esperados_son_los_estudiantes_de_los_grupos_del_examen(): void
    {
        $expected = $this->service()->expected($this->exam->id_examen)->get();

        $this->assertEqualsCanonicalizing(
            $this->rosterStudentIds($this->grupoPropioId),
            $expected->pluck('id_estudiante')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertSame([$this->grupoPropioId], $expected->pluck('id_grupo')->unique()->map(fn ($id) => (int) $id)->all());
        $this->assertNotNull($expected->first()->cod_sis);
    }

    public function test_no_incluye_estudiantes_de_grupos_que_el_examen_no_tiene(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 3);

        $this->assertSame(
            2,
            $this->service()->expected($this->exam->id_examen)->get()->count()
        );
    }

    public function test_no_escribe_examen_estudiante(): void
    {
        $this->service()->counts($this->exam->id_examen);
        $this->service()->pending($this->exam->id_examen)->get();

        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_al_inicio_todos_los_esperados_estan_pendientes(): void
    {
        $this->assertSame(
            ['esperados' => 2, 'ingresados' => 0, 'pendientes' => 2],
            $this->service()->counts($this->exam->id_examen)
        );
    }

    public function test_un_ingreso_registrado_deja_de_estar_pendiente(): void
    {
        [$first, $second] = $this->rosterStudentIds($this->grupoPropioId);

        $this->enter($this->exam, $first, $this->grupoPropioId);

        $this->assertSame(
            ['esperados' => 2, 'ingresados' => 1, 'pendientes' => 1],
            $this->service()->counts($this->exam->id_examen)
        );
        $this->assertSame(
            [$second],
            $this->service()->pending($this->exam->id_examen)->pluck('id_estudiante')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertSame(
            [$first],
            $this->service()->entered($this->exam->id_examen)->pluck('id_estudiante')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_un_ingreso_con_retraso_tambien_cuenta_como_ingresado(): void
    {
        [$first] = $this->rosterStudentIds($this->grupoPropioId);

        $this->enter($this->exam, $first, $this->grupoPropioId, 'CON_RETRASO');

        $this->assertSame(1, $this->service()->counts($this->exam->id_examen)['ingresados']);
        $this->assertSame(1, $this->service()->counts($this->exam->id_examen)['pendientes']);
    }

    public function test_la_nomina_que_cambia_se_refleja_sin_ninguna_copia(): void
    {
        $this->enrollStudents($this->grupoPropioId, 3);

        $this->assertSame(
            ['esperados' => 5, 'ingresados' => 0, 'pendientes' => 5],
            $this->service()->counts($this->exam->id_examen)
        );
    }

    public function test_un_estudiante_en_dos_grupos_del_examen_se_cuenta_una_sola_vez(): void
    {
        $otherGroup = Group::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '8',
            'gestion' => '2026',
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_periodo' => $this->periodoActivoId,
        ]);
        [$first] = $this->rosterStudentIds($this->grupoPropioId);

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $otherGroup->id_grupo,
            'id_estudiante' => $first,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => RecordStatus::ACTIVE,
        ]);
        $this->link($this->exam, $otherGroup->id_grupo);

        $expected = $this->service()->expected($this->exam->id_examen)->get();

        $this->assertCount(2, $expected);
        $this->assertSame(2, $this->service()->counts($this->exam->id_examen)['esperados']);
    }

    public function test_los_participantes_de_un_examen_no_se_mezclan_con_los_de_otro(): void
    {
        $other = $this->createExam(['nombre_examen' => 'Otro examen']);
        $this->link($other, $this->grupoPropioId);
        [$first] = $this->rosterStudentIds($this->grupoPropioId);

        $this->enter($other, $first, $this->grupoPropioId);

        $this->assertSame(0, $this->service()->counts($this->exam->id_examen)['ingresados']);
        $this->assertSame(2, $this->service()->counts($this->exam->id_examen)['pendientes']);
    }
}
