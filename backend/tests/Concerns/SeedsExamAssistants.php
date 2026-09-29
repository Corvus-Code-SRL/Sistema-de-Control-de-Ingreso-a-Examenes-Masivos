<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Support\RecordStatus;
use Illuminate\Support\Facades\DB;

/**
 * Tres auxiliares registrados y exámenes del docente con ellos habilitados (HU-09).
 *
 * Mientras no exista HU-08, la habilitación se escribe directo en examen_auxiliar.
 */
trait SeedsExamAssistants
{
    use SeedsExamCatalog;

    protected string $mariaId = '33333333-3333-4333-8333-000000000001';

    protected string $jorgeId = '33333333-3333-4333-8333-000000000002';

    protected string $danielaId = '33333333-3333-4333-8333-000000000003';

    protected function seedExamAssistants(): void
    {
        $this->seedExamCatalog();

        $people = [
            [$this->mariaId, 'María', 'López', 'Arnez', '201900233'],
            [$this->jorgeId, 'Jorge', 'Rocha', 'Vidal', '202000871'],
            [$this->danielaId, 'Daniela', 'Ferrufino', 'Soliz', '201800451'],
        ];

        foreach ($people as [$id, $name, $lastName, $secondLastName, $sis]) {
            DB::table('usuario')->insert([
                'id_usuario' => $id,
                'nombre' => $name,
                'apellido_paterno' => $lastName,
                'apellido_materno' => $secondLastName,
                'correo' => $sis . '@umss.edu',
                'contrasenia' => 'x',
                'cod_sis' => $sis,
                'estado' => RecordStatus::ACTIVE,
            ]);
        }
    }

    /** Examen con dos ambientes (691A y 692B) y los tres auxiliares habilitados. */
    protected function examWithAssistants(array $overrides = [], ?int $classroomId = null): Exam
    {
        $exam = $this->createExam($overrides, [$this->aulaId, $this->otraAulaId]);

        foreach ([$this->mariaId, $this->jorgeId, $this->danielaId] as $userId) {
            ExamAssistant::create([
                'id_examen' => $exam->id_examen,
                'id_usuario' => $userId,
                'id_usuario_docente_habilita' => $exam->id_usuario_docente,
                'id_ambiente' => $classroomId,
            ]);
        }

        return $exam;
    }

    protected function assertAssignedTo(Exam $exam, string $userId, ?int $classroomId): void
    {
        $this->assertSame(
            $classroomId,
            ExamAssistant::forExamAndAssistant($exam->id_examen, $userId)->firstOrFail()->id_ambiente
        );
    }
}