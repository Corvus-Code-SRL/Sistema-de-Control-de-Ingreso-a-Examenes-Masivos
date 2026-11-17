<?php

namespace Tests\Unit\Support;

use App\Support\Text\SpanishSortKey;
use PHPUnit\Framework\TestCase;

class SpanishSortKeyTest extends TestCase
{
    public function test_ignora_mayusculas_tildes_y_dieresis(): void
    {
        $this->assertSame(SpanishSortKey::make('alvarez'), SpanishSortKey::make('  ÁLVAREZ  '));
        $this->assertSame(SpanishSortKey::make('agüero'), SpanishSortKey::make('AGUERO'));
        $this->assertSame(SpanishSortKey::make('ÉÍÓÚ'), SpanishSortKey::make('eiou'));
    }

    public function test_ordena_la_enie_despues_de_todos_los_prefijos_con_ene(): void
    {
        $names = ['Ochoa', 'Ñúñez', 'Núñez', 'Nzz', 'N', 'Ñ', 'Navarro', 'Muñoz', 'Munoz'];

        usort($names, fn (string $left, string $right): int =>
            strcmp(SpanishSortKey::make($left), SpanishSortKey::make($right)));

        $this->assertSame(['Munoz', 'Muñoz', 'N', 'Navarro', 'Núñez', 'Nzz', 'Ñ', 'Ñúñez', 'Ochoa'], $names);
    }

    public function test_admite_caracteres_compuestos_y_descompuestos_sin_intl(): void
    {
        $this->assertSame(SpanishSortKey::make('Peña'), SpanishSortKey::make("Pen\u{0303}a"));
        $this->assertSame(SpanishSortKey::make('Ágüero'), SpanishSortKey::make("A\u{0301}gu\u{0308}ero"));
        $this->assertNotSame(SpanishSortKey::make('Peña'), SpanishSortKey::make('Pena'));
    }
}
