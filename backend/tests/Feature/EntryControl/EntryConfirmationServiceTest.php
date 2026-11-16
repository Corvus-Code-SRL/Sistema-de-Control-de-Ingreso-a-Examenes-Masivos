<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Services\EntryControl\EntryConfirmationService;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class EntryConfirmationServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private Exam $exam;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();

        // Examen EN_INGRESO con un solo ambiente y el grupo propio asignado.
        // RoomAssignmentService::prepare deja el snapshot listo (Redis) para
        // que VerificationService::verify() funcione.
        $this->exam = $this->createExam(
            ['estado' => Exam::EN_INGRESO],
            [$this->aulaId]
        );

        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->student = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->select('estudiante.*')
            ->firstOrFail();

        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);

        $this->actingAs(User::query()->findOrFail($this->docenteId));
    }

    public function test_confirmar_ingreso_con_fila_existente_la_actualiza(): void
    {
        // Fila creada por HU-031 (o por HU-028 en HABILITADO): sin ingreso aún.
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
            'hora_ingreso' => null,
            'id_ambiente' => null,
        ]);

        $result = app(EntryConfirmationService::class)->confirm(
            $this->exam,
            [
                'id_estudiante' => $this->student->id_estudiante,
                'cod_sis' => $this->student->cod_sis,
                'id_ambiente' => $this->aulaId,
            ],
            User::query()->findOrFail($this->docenteId)
        );

        $this->assertTrue($result['creado']);
        $this->assertSame('INGRESO_REGISTRADO', $result['veredicto']);

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'estado_ingreso' => 'INGRESO',
            'id_ambiente' => $this->aulaId,
        ]);
    }

    public function test_confirmar_ingreso_con_no_habilitado_es_rechazado(): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'observacion' => 'ASISTENCIA_INSUFICIENTE',
            'estado_ingreso' => 'NO_INGRESO',
        ]);

        // El snapshot se armó antes de insertar la fila. Lo reconstruimos
        // para que entries() vea el NO_HABILITADO.
        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);

        $result = app(EntryConfirmationService::class)->confirm(
            $this->exam,
            [
                'id_estudiante' => $this->student->id_estudiante,
                'cod_sis' => $this->student->cod_sis,
                'id_ambiente' => $this->aulaId,
            ],
            User::query()->findOrFail($this->docenteId)
        );

        $this->assertFalse($result['creado']);
        $this->assertSame('NO_HABILITADO', $result['veredicto']);

        // La fila no se toca.
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }
}
