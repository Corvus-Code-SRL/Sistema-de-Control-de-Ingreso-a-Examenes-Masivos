<?php

namespace Tests\Unit\Support;

use App\Support\Database\AccentInsensitiveSearch;
use PHPUnit\Framework\TestCase;

class AccentInsensitiveSearchTest extends TestCase
{
    public function test_normalize_quita_tildes_dieresis_y_enies_en_mayusculas_y_minusculas(): void
    {
        $this->assertSame('Jose Peruna', AccentInsensitiveSearch::normalize('José Perüña'));
        $this->assertSame('AEIOUUN aeiouun', AccentInsensitiveSearch::normalize('ÁÉÍÓÚÜÑ áéíóúüñ'));
    }

    public function test_normalize_recorta_los_espacios_y_un_termino_vacio_queda_vacio(): void
    {
        $this->assertSame('jose', AccentInsensitiveSearch::normalize('  jose '));
        $this->assertSame('', AccentInsensitiveSearch::normalize("  \t "));
    }

    public function test_pattern_escapa_los_comodines_para_buscarlos_como_texto(): void
    {
        $this->assertSame('%jose%', AccentInsensitiveSearch::pattern('jose'));
        $this->assertSame('%100\%%', AccentInsensitiveSearch::pattern('100%'));
        $this->assertSame('%a\_b%', AccentInsensitiveSearch::pattern('a_b'));
        $this->assertSame('%a' . str_repeat('\\', 2) . 'b%', AccentInsensitiveSearch::pattern('a' . '\\' . 'b'));
    }

    public function test_condition_normaliza_la_columna_con_un_unico_marcador(): void
    {
        $sql = AccentInsensitiveSearch::condition('estudiante.nombre');

        $this->assertStringStartsWith('translate(lower(estudiante.nombre), ', $sql);
        $this->assertStringEndsWith(' ILIKE ?', $sql);
        $this->assertSame(1, substr_count($sql, '?'));
    }
}
