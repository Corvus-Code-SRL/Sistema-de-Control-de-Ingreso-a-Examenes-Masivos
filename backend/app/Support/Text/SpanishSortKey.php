<?php

namespace App\Support\Text;

/** Clave alfabética española sin intl: ignora tildes y distingue la ñ después de la n. */
final class SpanishSortKey
{
    public static function make(string $value): string
    {
        $lower = mb_strtolower(trim($value), 'UTF-8');
        $composed = strtr($lower, ["n\u{0303}" => 'ñ']);

        return strtr($composed, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            "\u{0301}" => '', "\u{0308}" => '',
            // Codificar ambas letras evita confundir «na» con «ña» y conserva sus prefijos.
            'n' => 'n0', 'ñ' => 'n1',
        ]);
    }
}
