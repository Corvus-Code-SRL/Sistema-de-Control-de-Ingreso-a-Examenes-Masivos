<?php

namespace App\Services\Academic;

use App\Models\Group;
use App\Support\RecordStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carreras que atiende un grupo (HU-021, multicarrera).
 *
 * La carrera principal es grupo.id_carrera; las demás viven en grupo_oferta. La principal siempre
 * cuenta, aunque su fila de grupo_oferta falte (grupos creados antes de esta tabla o por otra vía).
 * Solo se puede sumar una carrera que oferta la misma materia en el período del grupo, con la oferta,
 * el par materia-carrera y la carrera activos.
 */
class GroupCareerService
{
    /**
     * Carreras del grupo, la principal primero y luego por nombre.
     *
     * @return array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}>
     */
    public function careersOf(Group $group): array
    {
        $principalId = (int) $group->id_carrera;

        // Una sola consulta: la principal y las de grupo_oferta.
        return $this->careerRows(
            DB::table('carrera')
                ->where('carrera.id_carrera', $principalId)
                ->orWhereIn('carrera.id_carrera', function ($query) use ($group): void {
                    $query->from('grupo_oferta')
                        ->select('id_carrera')
                        ->where('id_grupo', $group->id_grupo);
                }),
            $principalId
        );
    }

    /** @return array<int, int> */
    public function careerIdsOf(Group $group): array
    {
        return array_map(
            static fn (array $career): int => $career['id_carrera'],
            $this->careersOf($group)
        );
    }

    /**
     * Carreras que ofertan la materia en el período y que el grupo todavía no atiende: son las que
     * el docente puede sumar. Sin grupo, son todas las que ofertan la materia menos `$exceptCareerId`.
     *
     * @return array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}>
     */
    public function offeredCareers(int $subjectId, int $periodId, array $exceptCareerIds = []): array
    {
        $ids = DB::table('oferta_academica')
            ->join('carrera', 'carrera.id_carrera', '=', 'oferta_academica.id_carrera')
            ->join('materia_carrera', function ($join): void {
                $join->on('materia_carrera.id_carrera', '=', 'oferta_academica.id_carrera')
                    ->on('materia_carrera.id_materia', '=', 'oferta_academica.id_materia');
            })
            ->where('materia_carrera.estado', RecordStatus::ACTIVE)
            ->where('oferta_academica.id_materia', $subjectId)
            ->where('oferta_academica.id_gestion_periodo', $periodId)
            ->where('oferta_academica.estado', RecordStatus::ACTIVE)
            ->where('carrera.estado', RecordStatus::ACTIVE)
            ->whereNotIn('oferta_academica.id_carrera', $exceptCareerIds)
            ->pluck('oferta_academica.id_carrera')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $this->describe($ids, null);
    }

    /**
     * Deja en grupo_oferta exactamente la principal más `$additionalIds`. Quitar una carrera que
     * todavía tiene estudiantes inscritos en el grupo se rechaza: dejaría inscripciones huérfanas.
     *
     * @param array<int, int> $additionalIds
     *
     * @return array{antes: array<int, int>, despues: array<int, int>}
     *
     * @throws ValidationException
     */
    public function syncAdditional(Group $group, array $additionalIds): array
    {
        $principalId = (int) $group->id_carrera;
        $wanted = array_values(array_unique(array_map('intval', $additionalIds)));

        if (in_array($principalId, $wanted, true)) {
            throw ValidationException::withMessages([
                'carreras_adicionales' => ['La carrera principal del grupo no se repite como adicional.'],
            ]);
        }

        $this->assertOffered($group, $wanted);

        $before = $this->careerIdsOf($group);
        $currentAdditional = array_values(array_diff($before, [$principalId]));
        $toRemove = array_values(array_diff($currentAdditional, $wanted));
        $toAdd = array_values(array_diff($wanted, $currentAdditional));

        $this->assertWithoutStudents($group, $toRemove);

        if ($toRemove !== []) {
            DB::table('grupo_oferta')
                ->where('id_grupo', $group->id_grupo)
                ->whereIn('id_carrera', $toRemove)
                ->delete();
        }

        $rows = array_map(
            fn (int $careerId): array => $this->offeringRow($group, $careerId),
            array_merge([$principalId], $toAdd)
        );

        DB::table('grupo_oferta')->insertOrIgnore($rows);

        return [
            'antes' => $before,
            'despues' => array_values(array_merge([$principalId], $wanted)),
        ];
    }

    /**
     * @param array<int, int> $careerIds
     *
     * @throws ValidationException
     */
    private function assertOffered(Group $group, array $careerIds): void
    {
        if ($careerIds === []) {
            return;
        }

        $offered = DB::table('oferta_academica')
            ->join('carrera', 'carrera.id_carrera', '=', 'oferta_academica.id_carrera')
            ->join('materia_carrera', function ($join): void {
                $join->on('materia_carrera.id_carrera', '=', 'oferta_academica.id_carrera')
                    ->on('materia_carrera.id_materia', '=', 'oferta_academica.id_materia');
            })
            ->where('materia_carrera.estado', RecordStatus::ACTIVE)
            ->where('oferta_academica.id_materia', $group->id_materia)
            ->where('oferta_academica.id_gestion_periodo', $group->id_gestion_periodo)
            ->where('oferta_academica.estado', RecordStatus::ACTIVE)
            ->where('carrera.estado', RecordStatus::ACTIVE)
            ->whereIn('oferta_academica.id_carrera', $careerIds)
            ->pluck('oferta_academica.id_carrera')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if (array_diff($careerIds, $offered) === []) {
            return;
        }

        throw ValidationException::withMessages([
            'carreras_adicionales' => [
                'Una de las carreras no ofrece esta materia en el período del grupo.',
            ],
        ]);
    }

    /**
     * @param array<int, int> $careerIds
     *
     * @throws ValidationException
     */
    private function assertWithoutStudents(Group $group, array $careerIds): void
    {
        if ($careerIds === []) {
            return;
        }

        $withStudents = DB::table('grupo_estudiante')
            ->join('carrera', 'carrera.id_carrera', '=', 'grupo_estudiante.id_carrera')
            ->where('grupo_estudiante.id_grupo', $group->id_grupo)
            ->whereIn('grupo_estudiante.id_carrera', $careerIds)
            ->orderBy('carrera.nombre')
            ->value('carrera.nombre');

        if ($withStudents === null) {
            return;
        }

        throw ValidationException::withMessages([
            'carreras_adicionales' => [
                "No se puede quitar {$withStudents}: tiene estudiantes inscritos en este grupo.",
            ],
        ]);
    }

    /** @return array<string, int> */
    private function offeringRow(Group $group, int $careerId): array
    {
        return [
            'id_grupo' => (int) $group->id_grupo,
            'id_carrera' => $careerId,
            'id_materia' => (int) $group->id_materia,
            'id_gestion_periodo' => (int) $group->id_gestion_periodo,
        ];
    }

    /**
     * @param array<int, int> $ids
     *
     * @return array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}>
     */
    private function describe(array $ids, ?int $principalId): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->careerRows(DB::table('carrera')->whereIn('carrera.id_carrera', $ids), $principalId);
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return array<int, array{id_carrera: int, codigo: string, nombre: string, principal: bool}>
     */
    private function careerRows($query, ?int $principalId): array
    {
        return $query
            ->orderBy('carrera.nombre')
            ->get(['carrera.id_carrera', 'carrera.codigo', 'carrera.nombre'])
            ->map(static fn ($career): array => [
                'id_carrera' => (int) $career->id_carrera,
                'codigo' => (string) $career->codigo,
                'nombre' => (string) $career->nombre,
                'principal' => (int) $career->id_carrera === $principalId,
            ])
            ->sortBy(static fn (array $career): int => $career['principal'] ? 0 : 1)
            ->values()
            ->all();
    }
}
