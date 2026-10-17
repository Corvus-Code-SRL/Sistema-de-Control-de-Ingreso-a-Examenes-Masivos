<?php

namespace App\Support\Database;

/**
 * Búsqueda por texto sin distinguir mayúsculas ni tildes, sin depender de la extensión unaccent.
 *
 * translate() normaliza la columna y el término con el mismo mapa, de modo que «jose» encuentra
 * «José». Un % o _ escrito por el usuario se busca como texto literal, no como comodín.
 *
 * Uso: whereRaw(AccentInsensitiveSearch::condition('materia.nombre'), [AccentInsensitiveSearch::pattern($term)]).
 * La columna se interpola en el SQL: debe ser siempre un literal del código, nunca texto del usuario.
 */
final class AccentInsensitiveSearch
{
    /** Letras acentuadas y su equivalente sin acento. */
    private const ACCENTED = 'ÁÉÍÓÚÜÑáéíóúüñ';
    private const PLAIN = 'AEIOUUNaeiouun';

    /** Condición SQL con un solo marcador `?`, que recibe el resultado de pattern(). */
    public static function condition(string $column): string
    {
        return "translate(lower({$column}), '" . self::ACCENTED . "', '" . self::PLAIN . "') ILIKE ?";
    }

    /** Recorta el término y le quita las tildes; vacío significa «sin búsqueda». */
    public static function normalize(string $term): string
    {
        $plain = array_combine(
            preg_split('//u', self::ACCENTED, -1, PREG_SPLIT_NO_EMPTY),
            str_split(self::PLAIN)
        );

        return strtr(trim($term), $plain);
    }

    /** Patrón `%término%` listo para el marcador de condition(). */
    public static function pattern(string $normalizedTerm): string
    {
        return '%' . addcslashes($normalizedTerm, '\\%_') . '%';
    }
}
