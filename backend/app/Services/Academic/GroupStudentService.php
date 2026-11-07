<?php

namespace App\Services\Academic;

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Student;
use App\Support\Database\AccentInsensitiveSearch;
use App\Support\RecordStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Estudiantes inscritos en un grupo (HU-020): la lista que el docente consulta en el detalle.
 *
 * Solo cuentan las inscripciones ACTIVO. Es de solo lectura, así que no escribe en la bitácora.
 */
class GroupStudentService
{
    /** Filas por página: fija, el cliente no la elige. */
    public const PER_PAGE = 50;

    /** Palabras de la búsqueda que se consideran; el resto se ignora para acotar la consulta. */
    private const MAX_TERMS = 5;

    /** Nombre completo tal como lo lee una persona, para que «maria nunez» encuentre «María Núñez». */
    private const FULL_NAME = "CONCAT_WS(' ', estudiante.nombre, estudiante.apellido_paterno, "
        . "estudiante.apellido_materno)";

    /**
     * Una página de estudiantes del grupo, por apellido paterno, materno y nombre. `$search` coincide
     * con el nombre o el código SIS sin distinguir mayúsculas ni tildes; cada palabra debe coincidir.
     *
     * `total_inscritos` cuenta todas las inscripciones activas, sin la búsqueda: es el total del
     * encabezado, que no cambia mientras se busca. `$careerId` deja solo a los inscritos con esa
     * carrera (HU-021 multicarrera); `por_carrera` cuenta los inscritos de cada carrera del grupo.
     *
     * @return array{
     *     students: LengthAwarePaginator,
     *     total_inscritos: int,
     *     por_carrera: array<int, array{id_carrera: int, nombre: string, total: int}>
     * }
     *
     * @throws AuthorizationException si el grupo no lo dicta `$teacherId`
     */
    public function paginateForGroup(
        Group $group,
        string $teacherId,
        ?string $search,
        int $page,
        ?int $careerId = null
    ): array {
        if ((string) $group->id_usuario_docente !== $teacherId) {
            throw new AuthorizationException('Solo el docente que dicta el grupo puede ver su detalle.');
        }

        $query = Student::query()
            ->join('grupo_estudiante', 'grupo_estudiante.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('grupo_estudiante.id_grupo', $group->id_grupo)
            ->leftJoin('carrera', 'carrera.id_carrera', '=', 'grupo_estudiante.id_carrera')
            ->where('grupo_estudiante.estado', RecordStatus::ACTIVE)
            ->when($careerId !== null, function (Builder $query) use ($careerId): void {
                $query->where('grupo_estudiante.id_carrera', $careerId);
            })
            ->select([
                'estudiante.id_estudiante',
                'estudiante.cod_sis',
                'estudiante.nombre',
                'estudiante.apellido_paterno',
                'estudiante.apellido_materno',
                'grupo_estudiante.id_carrera',
                'carrera.nombre as carrera_nombre',
            ])
            ->orderByRaw(
                "lower(translate(estudiante.apellido_paterno, 'ÁÉÍÓÚÜáéíóúü', 'AEIOUUaeiouu')) ASC"
            )
            // Sin apellido materno va primero: «Pérez» antes que «Pérez Mamani».
            ->orderByRaw(
                "lower(translate(estudiante.apellido_materno, 'ÁÉÍÓÚÜáéíóúü', 'AEIOUUaeiouu')) ASC NULLS FIRST"
            )
            ->orderByRaw(
                "lower(translate(estudiante.nombre, 'ÁÉÍÓÚÜáéíóúü', 'AEIOUUaeiouu')) ASC"
            )
            // Desempate estable: sin él, dos homónimos podrían saltar de página.
            ->orderBy('estudiante.id_estudiante');

        foreach ($this->searchTerms($search) as $term) {
            $this->matchStudent($query, AccentInsensitiveSearch::pattern($term));
        }

        return [
            'students' => $query->paginate(self::PER_PAGE, ['*'], 'page', $page),
            'total_inscritos' => GroupStudent::query()
                ->where('id_grupo', $group->id_grupo)
                ->where('estado', RecordStatus::ACTIVE)
                ->count(),
            'por_carrera' => $this->countByCareer($group),
        ];
    }

    /** @return array<int, array{id_carrera: int, nombre: string, total: int}> */
    private function countByCareer(Group $group): array
    {
        return DB::table('grupo_estudiante')
            ->join('carrera', 'carrera.id_carrera', '=', 'grupo_estudiante.id_carrera')
            ->where('grupo_estudiante.id_grupo', $group->id_grupo)
            ->where('grupo_estudiante.estado', RecordStatus::ACTIVE)
            ->groupBy('grupo_estudiante.id_carrera', 'carrera.nombre')
            ->orderBy('carrera.nombre')
            ->get([
                'grupo_estudiante.id_carrera',
                'carrera.nombre',
                DB::raw('count(*) as total'),
            ])
            ->map(static fn ($row): array => [
                'id_carrera' => (int) $row->id_carrera,
                'nombre' => (string) $row->nombre,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /** @return array<int, string> */
    private function searchTerms(?string $search): array
    {
        $normalized = AccentInsensitiveSearch::normalize((string) $search);
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice($words, 0, self::MAX_TERMS);
    }

    private function matchStudent(Builder $query, string $pattern): void
    {
        $query->where(function (Builder $match) use ($pattern) {
            $match->whereRaw(AccentInsensitiveSearch::condition(self::FULL_NAME), [$pattern])
                ->orWhereRaw(AccentInsensitiveSearch::condition('estudiante.cod_sis'), [$pattern]);
        });
    }
}
