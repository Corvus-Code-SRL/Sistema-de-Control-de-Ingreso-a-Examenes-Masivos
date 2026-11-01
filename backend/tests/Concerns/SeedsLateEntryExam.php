<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\Role;
use App\Models\StudentExam;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Examen EN_CURSO para el ingreso con retraso (HU-037): dos ambientes de capacidad 1, así
 * el primer estudiante de la nómina corresponde a 691A y el segundo a 692B, y un auxiliar
 * habilitado en cada ambiente.
 */
trait SeedsLateEntryExam
{
    use SeedsExamCatalog;

    protected string $assistantAId = '55555555-5555-4555-8555-00000000000a';

    protected string $assistantBId = '55555555-5555-4555-8555-00000000000b';

    protected Exam $exam;

    protected int $firstStudentId;

    protected int $secondStudentId;

    protected function seedLateEntryExam(): void
    {
        $this->seedExamCatalog();

        $this->exam = $this->createExam(['estado' => Exam::EN_CURSO], [$this->aulaId, $this->otraAulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        DB::table('ambiente')
            ->whereIn('id_ambiente', [$this->aulaId, $this->otraAulaId])
            ->update(['capacidad' => 1]);

        [$this->firstStudentId, $this->secondStudentId] = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->createLateEntryAssistant($this->assistantAId, '202498001', $this->aulaId);
        $this->createLateEntryAssistant($this->assistantBId, '202498002', $this->otraAulaId);
    }

    protected function createLateEntryAssistant(string $id, string $sis, ?int $roomId, bool $enabled = true): void
    {
        DB::table('usuario')->insert([
            'id_usuario' => $id,
            'nombre' => 'Auxiliar',
            'apellido_paterno' => $sis,
            'correo' => "{$sis}@umss.edu",
            'contrasenia' => 'x',
            'cod_sis' => $sis,
            'estado' => RecordStatus::ACTIVE,
        ]);
        DB::table('usuario_rol')->insert([
            'id_usuario' => $id,
            'id_rol' => Role::query()->where('nombre_rol', Role::AUXILIAR)->value('id_rol'),
            'fecha_inicio' => now(),
        ]);

        if ($enabled) {
            DB::table('examen_auxiliar')->insert([
                'id_examen' => $this->exam->id_examen,
                'id_usuario' => $id,
                'id_usuario_docente_habilita' => $this->docenteId,
                'id_ambiente' => $roomId,
            ]);
        }
    }

    protected function lateEntryUrl(string $path, ?Exam $exam = null): string
    {
        $examId = ($exam ?? $this->exam)->id_examen;

        return "/api/control-ingreso/examenes/{$examId}/retrasos/{$path}";
    }

    protected function travelToLocal(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-10-31 {$time}:00", config('sciem.zona_horaria')));
    }

    protected function studentColumn(int $studentId, string $column): string
    {
        return (string) DB::table('estudiante')->where('id_estudiante', $studentId)->value($column);
    }

    protected function setLateEntryExamState(string $state): void
    {
        DB::table('examen')->where('id_examen', $this->exam->id_examen)->update(['estado' => $state]);
    }

    /** Ingreso ya registrado en HU-032, a la hora y en el ambiente dados. */
    protected function insertEntry(int $studentId, string $time, int $roomId): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $studentId,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => StudentExam::HABILITADO,
            'estado_ingreso' => StudentExam::INGRESO,
            'hora_ingreso' => $time,
            'id_ambiente' => $roomId,
        ]);
    }

    protected function entryOf(int $studentId): ?object
    {
        return DB::table('examen_estudiante')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->first();
    }
}
