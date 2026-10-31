<?php

namespace Tests\Unit\Support;

use App\Support\SisCode;
use PHPUnit\Framework\TestCase;

class SisCodeTest extends TestCase
{
    /**
     * @dataProvider canonicalForms
     */
    public function test_normaliza_a_la_forma_canonica(string $input, string $expected): void
    {
        $this->assertSame($expected, SisCode::normalize($input));
    }

    public function canonicalForms(): array
    {
        return [
            'docente de 5 digitos' => ['10452', '10452'],
            'estudiante de 9 digitos' => ['201800451', '201800451'],
            'administrador alfanumerico' => ['ADM0001', 'ADM0001'],
            'recorta los bordes' => ["  201800451\t", '201800451'],
            'pasa a mayusculas' => ['adm0001', 'ADM0001'],
            'colapsa espacios internos' => ["AB   12\t34", 'AB 12 34'],
            'espacio duro de Excel' => ["\u{00A0}201800451\u{00A0}", '201800451'],
            'conserva ceros a la izquierda' => ['000123456', '000123456'],
            'no impone solo digitos' => ['x-1', 'X-1'],
            'no impone longitud' => ['7', '7'],
        ];
    }

    public function test_es_idempotente(): void
    {
        $once = SisCode::normalize("  adm  0001 ");

        $this->assertSame($once, SisCode::normalize($once));
    }

    public function test_normalize_nullable_devuelve_null_para_null_o_vacio(): void
    {
        $this->assertNull(SisCode::normalizeNullable(null));
        $this->assertNull(SisCode::normalizeNullable("  \t "));
        $this->assertSame('ADM0001', SisCode::normalizeNullable(' adm0001 '));
    }
}
