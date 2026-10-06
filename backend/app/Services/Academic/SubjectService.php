<?php

namespace App\Services\Academic;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Consulta de solo lectura del catálogo institucional de materias para Administración. */
class SubjectService
{
    /** Letras acentuadas y su equivalente sin acento, para comparar sin distinguir tildes. */
    private const ACCENTED = 'ÁÉÍÓÚÜÑáéíóúüñ';
    private const PLAIN = 'AEIOUUNaeiouun';

    public function listForAdministration(?string $search = null): Collection
    {
        $query = Subject::query()->orderBy('nombre')->orderBy('id_materia');

        $term = $this->normalize((string) $search);

        if ($term !== '') {
            $this->applySearch($query, $term);
        }

        return $query->get();
    }

    /**
     * Compara sin distinguir mayúsculas ni tildes, sin depender de la extensión unaccent:
     * translate() normaliza las columnas y el término con el mismo mapa.
     */
    private function applySearch(Builder $query, string $term): void
    {
        $pattern = '%' . $this->escapeLike($term) . '%';
        $normalized = "translate(lower(%s), '" . self::ACCENTED . "', '" . self::PLAIN . "') ILIKE ?";

        $query->where(function (Builder $where) use ($normalized, $pattern) {
            $where->whereRaw(sprintf($normalized, 'materia.codigo'), [$pattern])
                ->orWhereRaw(sprintf($normalized, 'materia.nombre'), [$pattern]);
        });
    }

    private function normalize(string $term): string
    {
        $plain = array_combine(
            preg_split('//u', self::ACCENTED, -1, PREG_SPLIT_NO_EMPTY),
            str_split(self::PLAIN)
        );

        return strtr(trim($term), $plain);
    }

    /** Un % o _ escrito por el usuario se busca como texto literal, no como comodín. */
    private function escapeLike(string $term): string
    {
        return addcslashes($term, '\\%_');
    }
}
