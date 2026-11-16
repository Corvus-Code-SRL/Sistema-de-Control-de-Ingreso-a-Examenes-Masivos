<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\ExamInvitation;
use App\Models\Role;
use App\Support\RecordStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Escenario de HU-026 sobre el catálogo de SeedsExamCatalog: el docente autenticado ($docenteId) es el
 * creador y otroDocenteId dicta Cálculo II (el mismo par) en el período vigente.
 *
 * La clase que lo usa debe usar también SeedsExamCatalog y AssignsIncidentRole.
 */
trait SeedsCollaboratorInvitations
{
    private int $collaboratorGroupNumber = 50;

    /**
     * Cuenta de docente con, si se indica, un grupo de la materia dada. Devuelve su id_usuario.
     *
     * @param  array{rol?: string, estado?: string, periodo?: int, estado_grupo?: string, carrera?: int}  $options
     */
    protected function createTeacherAccount(
        string $name,
        string $paternal,
        string $sis,
        ?int $subjectId,
        array $options = []
    ): string {
        $id = (string) Str::uuid();

        DB::table('usuario')->insert([
            'id_usuario' => $id,
            'nombre' => $name,
            'apellido_paterno' => $paternal,
            'correo' => Str::slug($name . '.' . $paternal . '.' . $sis) . '@umss.edu',
            'contrasenia' => 'x',
            'cod_sis' => $sis,
            'estado' => $options['estado'] ?? RecordStatus::ACTIVE,
        ]);

        $this->assignRole($id, $options['rol'] ?? Role::DOCENTE);

        if ($subjectId !== null) {
            $careerId = $options['carrera'] ?? $this->sistemasId;
            $periodId = $options['periodo'] ?? $this->periodoActivoId;

            $this->offerPair($careerId, $subjectId, $periodId);

            DB::table('grupo')->insert([
                'id_carrera' => $careerId,
                'id_materia' => $subjectId,
                'num_grupo' => (string) ++$this->collaboratorGroupNumber,
                'estado' => $options['estado_grupo'] ?? RecordStatus::ACTIVE,
                'id_usuario_docente' => $id,
                'id_gestion_periodo' => $periodId,
            ]);
        }

        return $id;
    }

    /** Invitación escrita directamente, para preparar el escenario sin pasar por la API. */
    protected function createInvitation(
        Exam $exam,
        string $invitedId,
        string $state = ExamInvitation::PENDIENTE,
        array $overrides = []
    ): ExamInvitation {
        $attributes = [
            'id_examen' => $exam->id_examen,
            'id_docente_creador' => (string) $exam->id_usuario_docente,
            'id_docente_invitado' => $invitedId,
            'fecha_invitacion' => '2026-11-01 10:00:00-04',
            'estado' => $state,
            'permite_agregar_grupos' => true,
        ];

        $sentAt = Carbon::parse($overrides['fecha_invitacion'] ?? $attributes['fecha_invitacion']);

        if (in_array($state, [ExamInvitation::ACEPTADA, ExamInvitation::RECHAZADA], true)) {
            $attributes['fecha_respuesta'] = $sentAt->copy()->addDay();
        }

        if ($state === ExamInvitation::SIN_EFECTO) {
            $attributes['fecha_sin_efecto'] = $sentAt->copy()->addDays(2);
        }

        return ExamInvitation::create(array_merge($attributes, $overrides));
    }

    protected function invitationsUrl(Exam $exam): string
    {
        return "/api/docente/invitaciones/examenes/{$exam->id_examen}";
    }
}
