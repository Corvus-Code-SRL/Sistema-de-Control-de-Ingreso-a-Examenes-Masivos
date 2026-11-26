<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\Classroom;
use App\Models\Group;
use App\Models\Student;
use App\Services\Exams\ExamRosterService;
use App\Support\RecordStatus;
use App\Support\SisCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** Escenarios de HU-031 sobre el catálogo compartido, con nómina propia por examen. */
trait SeedsRoomDistribution
{
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private int $distributionGroupNumber = 100;

    /** @param list<int> $capacities */
    protected function distributionExam(int $count = 4, array $capacities = [30, 30]): Exam
    {
        $roomIds = [];

        foreach ($capacities as $index => $capacity) {
            $roomId = [$this->aulaId, $this->otraAulaId][$index] ?? (int) Classroom::create([
                'nro_aula' => 'D' . $this->distributionGroupNumber . '-' . $index,
                'capacidad' => $capacity, 'estado' => RecordStatus::ACTIVE,
            ])->id_ambiente;
            DB::table('ambiente')->where('id_ambiente', $roomId)->update(['capacidad' => $capacity]);
            $roomIds[] = $roomId;
        }

        $exam = $this->createExam([], $roomIds);
        $group = Group::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => (string) ++$this->distributionGroupNumber,
            'id_usuario_docente' => $this->docenteId,
            'id_gestion_periodo' => $this->periodoActivoId,
            'estado' => RecordStatus::ACTIVE,
        ]);
        DB::table('grupo_examen')->insert(['id_examen' => $exam->id_examen, 'id_grupo' => $group->id_grupo]);
        $this->enrollStudents((int) $group->id_grupo, $count);

        return $exam;
    }

    /** @return list<int> */
    protected function distributionStudentIds(Exam $exam): array
    {
        return app(ExamRosterService::class)->expected((int) $exam->id_examen)
            ->orderBy('estudiante.id_estudiante')->pluck('estudiante.id_estudiante')
            ->map(fn (int $id): int => $id)->all();
    }

    protected function distributionUrl(Exam $exam): string
    {
        return "/api/examenes/{$exam->id_examen}/distribucion";
    }

    protected function distributionVersion(Exam $exam): string
    {
        return $this->getJson($this->distributionUrl($exam))->assertOk()->json('data.version');
    }

    /** @return list<array{id_estudiante:int, id_ambiente:int}> */
    protected function distributionProposal(Exam $exam): array
    {
        $students = $this->postJson($this->distributionUrl($exam) . '/propuesta')
            ->assertOk()->json('data.estudiantes');

        return array_map(fn (array $student): array => [
            'id_estudiante' => $student['id_estudiante'],
            'id_ambiente' => $student['id_ambiente'],
        ], $students);
    }

    protected function saveDistribution(Exam $exam): TestResponse
    {
        $version = $this->distributionVersion($exam);
        $assignments = $this->distributionProposal($exam);

        return $this->putJson($this->distributionUrl($exam), [
            'version' => $version, 'asignaciones' => $assignments,
        ]);
    }

    protected function setDistributionEligibility(Exam $exam, int $studentId, string $state): void
    {
        $row = app(ExamRosterService::class)->expected((int) $exam->id_examen)
            ->where('estudiante.id_estudiante', $studentId)->first();
        app(ExamRosterService::class)->ensureRow((int) $exam->id_examen, $studentId, (int) $row->id_grupo);
        DB::table('examen_estudiante')->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)->update(['estado_habilitacion' => $state]);
    }

    protected function addDistributionStudent(Exam $exam): int
    {
        $student = Student::create([
            'cod_sis' => SisCode::normalize('202603199'),
            'nombre' => 'Nuevo',
            'apellido_paterno' => 'Zapata',
            'estado' => RecordStatus::ACTIVE,
        ]);
        $groupId = DB::table('grupo_examen')->where('id_examen', $exam->id_examen)->value('id_grupo');
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $groupId, 'id_estudiante' => $student->id_estudiante,
            'fecha_inscripcion' => '2026-11-17', 'estado' => RecordStatus::ACTIVE,
        ]);

        return (int) $student->id_estudiante;
    }
}
