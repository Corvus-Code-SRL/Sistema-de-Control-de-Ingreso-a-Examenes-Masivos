<?php

namespace Tests\Concerns;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamType;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Database\Seeders\ActionSeeder;
use Database\Seeders\ExamTypeSeeder;

/**
 * Catálogos que usan las pruebas de exámenes, sobre el catálogo académico.
 *
 * El docente de config('sciem.docente_fijo_id') es quien crea los exámenes, y el par
 * (Sistemas, Cálculo II) es el que dicta en el periodo activo.
 */
trait SeedsExamCatalog
{
    use SeedsAcademicCatalog;

    protected int $aulaId;

    protected int $otraAulaId;

    protected int $aulaInactivaId;

    protected function seedExamCatalog(): void
    {
        $this->seedAcademicCatalog();
        $this->seed([ExamTypeSeeder::class, ActionSeeder::class]);

        $this->aulaId = $this->createClassroom('691A', RecordStatus::ACTIVE);
        $this->otraAulaId = $this->createClassroom('692B', RecordStatus::ACTIVE);
        $this->aulaInactivaId = $this->createClassroom('OLD-1', RecordStatus::INACTIVE);

        // Dos estudiantes activos y uno retirado: solo los activos rinden el examen.
        $this->enrollStudents($this->grupoPropioId, 2, 1);
    }

    /** Una fecha segura en el futuro, en el formato que espera la API. */
    protected function futureDate(int $days = 7): string
    {
        return Carbon::now(config('sciem.zona_horaria'))->addDays($days)->toDateString();
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre_examen' => 'Primer parcial',
            'id_carrera'    => $this->sistemasId,
            'id_materia'    => $this->calculoId,
            'categoria'     => 'REGULAR',
            'fecha'         => $this->futureDate(),
            'hora_inicio'   => '08:00',
            'duracion'      => 90,
            'ambientes'     => [$this->aulaId],
            'grupos'        => [$this->grupoPropioId],
            'normas'        => 'Sin celulares.',
        ], $overrides);
    }

    /**
     * Examen guardado sin pasar por la API, para preparar el escenario (otro docente,
     * otro estado, un horario ya ocupado).
     */
    protected function createExam(array $overrides = [], array $classroomIds = []): Exam
    {
        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            ($overrides['fecha'] ?? $this->futureDate()) . ' ' . ($overrides['hora_inicio'] ?? '08:00')
        );
        $duration = $overrides['duracion'] ?? 90;

        $exam = Exam::create(array_merge([
            'nombre_examen'      => 'Examen existente',
            'fecha'              => $startsAt->toDateString(),
            'hora_inicio'        => $startsAt->format('H:i'),
            'hora_fin'           => $startsAt->copy()->addMinutes($duration)->format('H:i'),
            'duracion'           => $duration,
            'id_tipo_examen'     => $this->examTypeId('REGULAR'),
            'id_carrera'         => $this->sistemasId,
            'id_materia'         => $this->calculoId,
            'id_usuario_docente' => $this->docenteId,
            'estado'             => Exam::PROGRAMADO,
        ], $overrides));

        if ($classroomIds !== []) {
            $exam->classrooms()->attach($classroomIds);
        }

        return $exam;
    }

    protected function examTypeId(string $category): int
    {
        return (int) ExamType::where('categoria', $category)->value('id_tipo_examen');
    }

    private function createClassroom(string $number, string $status): int
    {
        return Classroom::create([
            'nro_aula'  => $number,
            'capacidad' => 60,
            'ubicacion' => 'Edificio Nuevo',
            'estado'    => $status,
        ])->id_ambiente;
    }
}
