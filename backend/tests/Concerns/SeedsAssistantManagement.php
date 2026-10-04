<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\RecordStatus;
use Database\Seeders\ActionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de prueba de la gestión de auxiliares (HU-08).
 *
 * Sobre el catálogo académico: dos grupos propios del docente en (Sistemas, Cálculo II),
 * un grupo de otro docente y un examen PROGRAMADO del docente vinculado al primer grupo.
 */
trait SeedsAssistantManagement
{
    use SeedsAcademicCatalog;

    protected int $segundoGrupoPropioId;

    protected int $examId;

    protected function seedAssistantManagement(): void
    {
        $this->seedAcademicCatalog();
        $this->seed(ActionSeeder::class);

        $this->segundoGrupoPropioId = (int) Group::where('id_usuario_docente', $this->docenteId)
            ->where('id_periodo', $this->periodoActivoId)
            ->where('num_grupo', '2')
            ->value('id_grupo');

        $this->examId = $this->createExamLinkedTo([$this->grupoPropioId]);
    }

    protected function createAssistant(string $name, string $sis): string
    {
        $user = User::create([
            'nombre' => $name,
            'apellido_paterno' => 'Auxiliar',
            'correo' => strtolower($name) . '.' . $sis . '@test.com',
            'contrasenia' => 'x',
            'cod_sis' => $sis,
            'estado' => RecordStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            ['nombre_rol' => Role::AUXILIAR],
            ['descripcion' => 'Auxiliar de docencia', 'estado' => RecordStatus::ACTIVE]
        );

        UserRole::create([
            'id_usuario' => $user->id_usuario,
            'id_rol' => $role->id_rol,
            'fecha_inicio' => now(),
        ]);

        return $user->id_usuario;
    }

    /** @param array<int, int> $groupIds */
    protected function createExamLinkedTo(
        array $groupIds,
        string $state = Exam::PROGRAMADO,
        ?string $teacherId = null,
        string $name = 'Parcial 1'
    ): int {
        $typeId = DB::table('tipo_examen')->value('id_tipo_examen');

        if ($typeId === null) {
            $typeId = DB::table('tipo_examen')->insertGetId([
                'nombre' => 'Parcial',
                'categoria' => 'REGULAR',
            ], 'id_tipo_examen');
        }

        $exam = Exam::create([
            'nombre_examen' => $name,
            'fecha' => '2026-10-15',
            'hora_inicio' => '08:00',
            'id_tipo_examen' => $typeId,
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'id_usuario_docente' => $teacherId ?? $this->docenteId,
            'estado' => $state,
        ]);

        foreach ($groupIds as $groupId) {
            DB::table('grupo_examen')->insert(['id_grupo' => $groupId, 'id_examen' => $exam->id_examen]);
        }

        return (int) $exam->id_examen;
    }

    protected function putInGroup(string $assistantId, int $groupId): void
    {
        DB::table('grupo_auxiliar')->insert([
            'id_grupo' => $groupId,
            'id_usuario' => $assistantId,
            'fecha_incorporacion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    protected function enableInExam(string $assistantId, int $examId, ?string $teacherId = null): void
    {
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $examId,
            'id_usuario' => $assistantId,
            'id_usuario_docente_habilita' => $teacherId ?? $this->docenteId,
            'fecha_habilitacion' => now(),
            'id_ambiente' => null,
        ]);
    }

    protected function isEnabledInExam(string $assistantId, int $examId): bool
    {
        return DB::table('examen_auxiliar')
            ->where('id_examen', $examId)
            ->where('id_usuario', $assistantId)
            ->exists();
    }

    protected function groupStatus(string $assistantId, int $groupId): ?string
    {
        return DB::table('grupo_auxiliar')
            ->where('id_grupo', $groupId)
            ->where('id_usuario', $assistantId)
            ->value('estado');
    }

    protected function enrollStudentWithSis(int $groupId, string $sis): void
    {
        $studentId = DB::table('estudiante')->insertGetId([
            'cod_sis' => $sis,
            'ci' => substr($sis, 0, 10),
            'nombre' => 'Estudiante',
            'apellido_paterno' => $sis,
            'estado' => RecordStatus::ACTIVE,
        ], 'id_estudiante');

        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $groupId,
            'id_estudiante' => $studentId,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => RecordStatus::ACTIVE,
        ]);
    }
}
