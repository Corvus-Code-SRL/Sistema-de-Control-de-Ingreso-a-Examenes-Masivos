<?php

namespace Tests\Unit\Models;

use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Models\User;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class ExamAssistantTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private string $auxiliarId = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();

        DB::table('usuario')->insert([
            'id_usuario' => $this->auxiliarId,
            'nombre' => 'Daniela',
            'apellido_paterno' => 'Ferrufino',
            'apellido_materno' => 'Soliz',
            'correo' => 'daniela.ferrufino@umss.edu',
            'contrasenia' => 'x',
            'cod_sis' => '201800451',
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    public function test_mapea_sus_campos_y_empieza_sin_ambiente(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        $this->enableAssistant($exam);

        $assistant = ExamAssistant::forExamAndAssistant($exam->id_examen, $this->auxiliarId)->firstOrFail();

        $this->assertSame($exam->id_examen, $assistant->id_examen);
        $this->assertSame($this->auxiliarId, $assistant->id_usuario);
        $this->assertSame($this->docenteId, $assistant->id_usuario_docente_habilita);
        $this->assertNull($assistant->id_ambiente);
        $this->assertNotNull($assistant->fecha_habilitacion);
    }

    public function test_mapea_sus_relaciones(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);
        $this->enableAssistant($exam, $this->aulaId);

        $assistant = $exam->assistants()
            ->with(['exam', 'assistant', 'enabledBy', 'classroom'])
            ->firstOrFail();

        $this->assertInstanceOf(Exam::class, $assistant->exam);
        $this->assertSame($this->auxiliarId, $assistant->assistant->id_usuario);
        $this->assertSame($this->docenteId, $assistant->enabledBy->id_usuario);
        $this->assertSame('691A', $assistant->classroom->nro_aula);
        $this->assertCount(1, User::findOrFail($this->auxiliarId)->assistantAssignments);
    }

    private function enableAssistant(Exam $exam, ?int $classroomId = null): void
    {
        ExamAssistant::create([
            'id_examen' => $exam->id_examen,
            'id_usuario' => $this->auxiliarId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $classroomId,
        ]);
    }
}
