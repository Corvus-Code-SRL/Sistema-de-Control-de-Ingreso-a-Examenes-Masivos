<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\ExamInvitation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Docentes de un examen (HU-027): el docente que lo creó y los colaboradores que aceptaron su invitación.
 *
 * El colaborador controla el ingreso y registra retrasos, situaciones e incidencias como el creador, y ve el
 * historial y el reporte final del examen. Lo que solo puede hacer el creador (editar, cancelar, invitar,
 * asignar grupos, iniciar y terminar) sigue comprobando `examen.id_usuario_docente` en su propio servicio.
 *
 * Un colaborador es una fila de invitacion_examen en estado ACEPTADA: no hay otra tabla.
 */
class ExamCollaboratorService
{
    /** Si el usuario es docente del examen: su creador o un colaborador que aceptó la invitación. */
    public function isTeacherOf(Exam $exam, string $userId): bool
    {
        if ($userId === '') {
            return false;
        }

        if ((string) $exam->id_usuario_docente === $userId) {
            return true;
        }

        return $this->isCollaborator((int) $exam->id_examen, $userId);
    }

    /** Si el usuario aceptó una invitación a colaborar en el examen. */
    public function isCollaborator(int $examId, string $userId): bool
    {
        return ExamInvitation::query()
            ->where('id_examen', $examId)
            ->where('id_docente_invitado', $userId)
            ->where('estado', ExamInvitation::ACEPTADA)
            ->exists();
    }

    /**
     * Colaboradores del examen (ids de usuario), sin el creador.
     *
     * @return array<int, string>
     */
    public function collaboratorIds(int $examId): array
    {
        return ExamInvitation::query()
            ->where('id_examen', $examId)
            ->where('estado', ExamInvitation::ACEPTADA)
            ->orderBy('id_invitacion')
            ->pluck('id_docente_invitado')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Si la lista del examen se muestra separada por grupo: algún colaborador aceptó una invitación cuyo docente
     * creador marcó «lista separada por grupo» (HU-026/027). La nómina es la misma; solo cambia cómo se muestra.
     */
    public function listsByGroup(int $examId): bool
    {
        return ExamInvitation::query()
            ->where('id_examen', $examId)
            ->where('estado', ExamInvitation::ACEPTADA)
            ->where('lista_por_grupo', true)
            ->exists();
    }

    /**
     * Grupos del examen con su docente, ordenados por número de grupo.
     *
     * @return array<int, array{id_grupo: int, num_grupo: string, docente: string|null}>
     */
    public function examGroups(int $examId): array
    {
        return DB::table('grupo_examen as gx')
            ->join('grupo as g', 'g.id_grupo', '=', 'gx.id_grupo')
            ->leftJoin('usuario as u', 'u.id_usuario', '=', 'g.id_usuario_docente')
            ->where('gx.id_examen', $examId)
            // «2» antes que «10»: primero por largo y luego por texto.
            ->orderByRaw('length(g.num_grupo), g.num_grupo')
            ->get(['g.id_grupo', 'g.num_grupo', 'u.nombre', 'u.apellido_paterno', 'u.apellido_materno'])
            ->map(fn ($row) => [
                'id_grupo' => (int) $row->id_grupo,
                'num_grupo' => (string) $row->num_grupo,
                'docente' => $row->nombre === null ? null : trim(implode(' ', array_filter([
                    $row->nombre,
                    $row->apellido_paterno,
                    $row->apellido_materno,
                ]))),
            ])
            ->all();
    }

    /**
     * Agrega a una consulta sobre `examen` la condición «el usuario es docente del examen», como un OR dentro de
     * un grupo: `$query->where(fn ($q) => $this->whereTaughtBy($q, $userId))`.
     *
     * @param EloquentBuilder|QueryBuilder $query
     * @param string $examTable alias de la tabla examen en la consulta
     * @return EloquentBuilder|QueryBuilder
     */
    public function whereTaughtBy($query, string $userId, string $examTable = 'examen')
    {
        return $query->where("{$examTable}.id_usuario_docente", $userId)
            ->orWhereExists(function (QueryBuilder $accepted) use ($userId, $examTable) {
                $accepted->selectRaw('1')
                    ->from('invitacion_examen as ie_colaborador')
                    ->whereColumn('ie_colaborador.id_examen', "{$examTable}.id_examen")
                    ->where('ie_colaborador.id_docente_invitado', $userId)
                    ->where('ie_colaborador.estado', ExamInvitation::ACEPTADA);
            });
    }
}
