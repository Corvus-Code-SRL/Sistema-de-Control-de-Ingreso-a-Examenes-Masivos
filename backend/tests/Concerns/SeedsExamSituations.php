<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\ExamSituation;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ExamSituationActionSeeder;
use Illuminate\Support\Facades\DB;

/** Escenario local de un examen en curso con un estudiante presente y dos aulas. */
trait SeedsExamSituations
{
    use SeedsExamCatalog;

    protected Exam $situationExam;
    protected Student $situationStudent;

    protected function seedExamSituations(): void
    {
        $this->seedExamCatalog();
        $this->seed(ExamSituationActionSeeder::class);
        $this->situationExam = $this->createExam(
            ['estado' => Exam::EN_CURSO],
            [$this->aulaId, $this->otraAulaId]
        );

        DB::table('grupo_examen')->insert([
            'id_examen' => $this->situationExam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->situationStudent = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->select('estudiante.*')
            ->firstOrFail();

        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->situationExam->id_examen,
            'id_estudiante' => $this->situationStudent->id_estudiante,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'HABILITADO',
            'estado_ingreso' => 'INGRESO',
            'hora_ingreso' => '08:00:00',
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->docenteId,
            'registrado_en' => '2026-10-27 12:00:00+00:00',
        ]);
    }

    protected function situationUrl(?int $studentId = null): string
    {
        $studentId = $studentId ?? (int) $this->situationStudent->id_estudiante;

        return "/api/examenes/{$this->situationExam->id_examen}/estudiantes/{$studentId}/situaciones";
    }

    protected function situationListUrl(): string
    {
        return "/api/examenes/{$this->situationExam->id_examen}/situaciones";
    }

    protected function situationPayload(array $overrides = []): array
    {
        return array_merge([
            'tipo' => 'CAMBIO_AMBIENTE',
            'motivo' => 'Ruido',
            'id_ambiente_destino' => $this->otraAulaId,
        ], $overrides);
    }

    protected function actAsSituationAssistant(?int $roomId): void
    {
        DB::table('usuario_rol')->where('id_usuario', $this->otroDocenteId)->update([
            'id_rol' => Role::query()->where('nombre_rol', Role::AUXILIAR)->value('id_rol'),
        ]);

        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->situationExam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $roomId,
        ]);

        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
    }

    protected function createSituationFixture(array $overrides = []): ExamSituation
    {
        $id = DB::table('situacion_examen')->insertGetId(array_merge([
            'id_examen' => $this->situationExam->id_examen,
            'id_estudiante' => $this->situationStudent->id_estudiante,
            'tipo' => 'IDENTIFICACION',
            'id_ambiente_origen' => $this->aulaId,
            'id_ambiente_destino' => null,
            'motivo' => 'Documento deteriorado.',
            'registrado_en' => '2026-10-27 12:30:00+00:00',
            'id_usuario_registrante' => $this->docenteId,
        ], $overrides), 'id_situacion');

        return ExamSituation::query()->findOrFail($id);
    }
}