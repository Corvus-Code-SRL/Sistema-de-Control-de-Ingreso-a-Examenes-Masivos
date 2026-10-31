<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * recordFor() arma desde PostgreSQL el mismo registro que stage() deja en Redis: el
 * control normal y el ingreso con retraso verifican sobre los mismos datos.
 */
class EntryRecordBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private Exam $exam;
    private EntryControlSnapshotService $snapshots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        $this->exam = $this->createExam([], [$this->aulaId, $this->otraAulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        DB::table('ambiente')
            ->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])
            ->update(['capacidad' => 1]);

        $this->snapshots = app(EntryControlSnapshotService::class);
    }

    /** @return array<int, int> */
    private function rosterIds(): array
    {
        return DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function test_coincide_con_el_registro_del_snapshot(): void
    {
        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
        $examId = (int) $this->exam->id_examen;

        foreach ($this->rosterIds() as $studentId) {
            $this->assertSame(
                $this->snapshots->studentById($examId, $studentId),
                $this->snapshots->recordFor($this->exam, $studentId)
            );
        }
    }

    public function test_asigna_el_mismo_ambiente_por_capacidad(): void
    {
        [$first, $second] = $this->rosterIds();

        $this->assertSame('691A', $this->snapshots->recordFor($this->exam, $first)['ambiente_asignado']['nro_aula']);
        $this->assertSame('692B', $this->snapshots->recordFor($this->exam, $second)['ambiente_asignado']['nro_aula']);
    }

    public function test_refleja_el_ingreso_previo(): void
    {
        [$studentId] = $this->rosterIds();
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '10:01:00',
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->docenteId,
            'registrado_en' => '2026-10-31 14:01:00+00',
        ]);

        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
        $record = $this->snapshots->recordFor($this->exam, $studentId);

        $this->assertSame('10:01:00', $record['ingreso_previo']['hora_ingreso']);
        $this->assertSame('691A', $record['ingreso_previo']['nro_aula']);
        $this->assertSame('HABILITADO', $record['estado_habilitacion']);
        $this->assertSame(
            $this->snapshots->studentById((int) $this->exam->id_examen, $studentId),
            $record
        );
    }

    public function test_devuelve_null_para_un_estudiante_ajeno_al_examen(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);
        $outsiderId = (int) DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoAjenoId)
            ->value('id_estudiante');

        $this->assertNull($this->snapshots->recordFor($this->exam, $outsiderId));
    }
}
