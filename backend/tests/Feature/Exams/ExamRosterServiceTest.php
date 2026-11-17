<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Services\Exams\ExamRosterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamRosterServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private ExamRosterService $roster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
        $this->actAsTeacher($this->docenteId);
        $this->roster = app(ExamRosterService::class);
    }

    public function test_expected_devuelve_estudiante_sin_fila_como_habilitado(): void
    {
        // seedExamCatalog ya inscribe 2 estudiantes en grupoPropioId.
        // No sembramos nómina extra: usamos esos 2 y solo creamos el examen
        // con el grupo asignado, sin tocar examen_estudiante.
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count(),
            'El escenario debe empezar sin filas en examen_estudiante.'
        );

        $rows = $this->roster->expected($exam->id_examen)
            ->orderBy('estudiante.id_estudiante')
            ->get();

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('HABILITADO', $row->estado_habilitacion);
            $this->assertSame('NO_INGRESO', $row->estado_ingreso);
            $this->assertNull($row->observacion);
            $this->assertNull($row->hora_ingreso);
            $this->assertNull($row->id_ambiente);
        }
    }

    public function test_expected_respeta_no_habilitado(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        // La fila no existe: la creamos con el estado NO_HABILITADO.
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => 'ASISTENCIA_INSUFICIENTE',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        $row = $this->roster->expected($exam->id_examen)
            ->where('estudiante.id_estudiante', $studentId)
            ->first();

        $this->assertNotNull($row, 'expected() debe devolver al estudiante del grupo.');
        $this->assertSame('NO_HABILITADO', $row->estado_habilitacion);
        $this->assertSame('ASISTENCIA_INSUFICIENTE', $row->observacion);
        $this->assertSame('NO_INGRESO', $row->estado_ingreso);
    }

    public function test_expected_respeta_ingreso_previo(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '08:15:00',
        ]);

        $row = $this->roster->expected($exam->id_examen)
            ->where('estudiante.id_estudiante', $studentId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('INGRESO', $row->estado_ingreso);
        $this->assertSame('08:15:00', $row->hora_ingreso);
    }

    public function test_expected_en_finalizado_lee_solo_examen_estudiante(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentIds = $this->studentIdsInGroup();
        $this->assertGreaterThanOrEqual(2, count($studentIds));

        // Solo uno queda en examen_estudiante (foto congelada simulada).
        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentIds[0],
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        DB::table('examen')->where('id_examen', $exam->id_examen)->update(['estado' => Exam::FINALIZADO]);

        $rows = $this->roster->expected($exam->id_examen)->get();

        $this->assertCount(1, $rows);
        $this->assertSame($studentIds[0], (int) $rows->first()->id_estudiante);
    }

    public function test_expected_agrupa_estudiante_en_dos_grupos_una_sola_vez(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        // Segundo grupo del mismo par materia-carrera y período, para poder
        // inscribir al mismo estudiante sin violar unicidad de (carrera, materia, num_grupo, período).
        $segundoGrupoId = (int) DB::table('grupo')
            ->where('id_carrera', $this->sistemasId)
            ->where('id_materia', $this->calculoId)
            ->where('num_grupo', '2')
            ->value('id_grupo');

        $this->assertNotNull($segundoGrupoId, 'El seed debe tener el grupo 2 del par Sistemas/Cálculo.');

        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $segundoGrupoId,
        ]);
        DB::table('grupo_estudiante')->insertOrIgnore([
            'id_grupo' => $segundoGrupoId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => 'ACTIVO',
        ]);

        // El estudiante bajo prueba debe aparecer una sola vez, aunque esté
        // en dos grupos del examen. No asumimos el total: el seed ya inscribe
        // otros estudiantes en grupoPropioId.
        $rows = $this->roster->expected($exam->id_examen)
            ->where('estudiante.id_estudiante', $studentId)
            ->get();

        $this->assertCount(1, $rows);
    }

    public function test_ensure_row_crea_si_no_existe(): void
    {
        $exam = $this->createExam();
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        $this->assertSame(
            0,
            DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)->count()
        );

        $inserted = $this->roster->ensureRow(
            (int) $exam->id_examen,
            $studentId,
            (int) $this->grupoPropioId
        );

        $this->assertTrue($inserted);
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }

    public function test_ensure_row_no_pisa_si_existe(): void
    {
        // El examen debe tener el ambiente asociado en examen_ambiente para
        // poder guardar id_ambiente en examen_estudiante (FK fk_ingreso_examen_ambiente).
        $exam = $this->createExam([], [$this->aulaId]);
        DB::table('grupo_examen')->insertOrIgnore([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $studentId = $this->studentIdsInGroup()[0];

        DB::table('examen_estudiante')->insert([
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '08:15:00',
            'id_ambiente' => $this->aulaId,
        ]);

        $inserted = $this->roster->ensureRow(
            (int) $exam->id_examen,
            $studentId,
            (int) $this->grupoPropioId
        );

        $this->assertFalse($inserted);
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $exam->id_examen,
            'id_estudiante' => $studentId,
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '08:15:00',
            'id_ambiente' => $this->aulaId,
        ]);
    }

    /**
     * Estudiantes inscritos en el grupo propio según grupo_estudiante,
     * ordenados por id. Es la fuente de verdad de la nómina antes de
     * tocar examen_estudiante.
     *
     * @return array<int, int>
     */
    private function studentIdsInGroup(): array
    {
        return DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
