<?php

namespace App\Services\Academic;

use App\Models\Subject;
use App\Support\Database\AccentInsensitiveSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Consulta de solo lectura del catálogo institucional de materias para Administración. */
class SubjectService
{
    public function listForAdministration(?string $search = null): Collection
    {
        $query = Subject::query()->orderBy('nombre')->orderBy('id_materia');

        $term = AccentInsensitiveSearch::normalize((string) $search);

        if ($term !== '') {
            $this->applySearch($query, $term);
        }

        return $query->get();
    }

    private function applySearch(Builder $query, string $term): void
    {
        $pattern = AccentInsensitiveSearch::pattern($term);

        $query->where(function (Builder $where) use ($pattern) {
            $where->whereRaw(AccentInsensitiveSearch::condition('materia.codigo'), [$pattern])
                ->orWhereRaw(AccentInsensitiveSearch::condition('materia.nombre'), [$pattern]);
        });
    }
}
