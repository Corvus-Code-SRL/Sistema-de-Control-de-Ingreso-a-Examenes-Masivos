<?php

namespace Tests\Unit\Support;

use App\Models\Role;
use App\Support\SisCodeFormat;
use Carbon\Carbon;
use Tests\TestCase;

class SisCodeFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @dataProvider assistantCodes */
    public function test_formato_de_auxiliar(string $code, bool $valid): void
    {
        $this->assertSame($valid, SisCodeFormat::isValid($code, Role::AUXILIAR), $code);
    }

    public function assistantCodes(): array
    {
        return [
            'año actual' => ['202645678', true],
            'año 2000' => ['200012345', true],
            'primer año admitido' => ['199012345', true],
            'año anterior a 1990' => ['198912345', false],
            'año futuro' => ['202712345', false],
            'legado de 8 dígitos 1990-1999' => ['98123456', true],
            'legado de 8 dígitos 9x' => ['90000001', true],
            '8 dígitos que no son del 90 al 99' => ['20123456', false],
            '8 dígitos con prefijo 8' => ['89123456', false],
            '10 dígitos' => ['2023123456', false],
            '9 dígitos con letras' => ['2023A2345', false],
            'docente de 5 dígitos' => ['10452', false],
            'administrativo' => ['ADM0001', false],
        ];
    }

    /** @dataProvider administratorCodes */
    public function test_formato_de_administrador(string $code, bool $valid): void
    {
        $this->assertSame($valid, SisCodeFormat::isValid($code, Role::ADMINISTRADOR), $code);
    }

    public function administratorCodes(): array
    {
        return [
            ['ADM0001', true],
            ['ADM13352440', true],
            ['A1', true],
            ['ADM', false],
            ['0001', false],
            ['ADM00A1', false],
            ['ADM-0001', false],
            ['202312345', false],
            ['ABCDEFGHIJKL12345', false],
        ];
    }

    /** @dataProvider teacherCodes */
    public function test_formato_de_docente(string $code, bool $valid): void
    {
        $this->assertSame($valid, SisCodeFormat::isValid($code, Role::DOCENTE), $code);
    }

    public function teacherCodes(): array
    {
        return [
            'numérico existente' => ['10452', true],
            'tres caracteres' => ['ABC', true],
            'alfanumérico' => ['DOC2A9', true],
            'quince caracteres' => ['ABCDE12345FGHIJ', true],
            'dos caracteres' => ['AB', false],
            'dieciséis caracteres' => ['ABCDE12345FGHIJK', false],
            'con guion' => ['DOC-01', false],
            'con espacio' => ['DOC 01', false],
            'vacío' => ['', false],
        ];
    }

    /** @dataProvider anyRoleCodes */
    public function test_sin_rol_acepta_cualquiera_de_los_formatos(string $code, bool $valid): void
    {
        $this->assertSame($valid, SisCodeFormat::isValid($code, null), $code);
    }

    public function anyRoleCodes(): array
    {
        return [
            ['202312345', true],
            ['98123456', true],
            ['ADM13352440', true],
            ['10452', true],
            ['DOC2A9', true],
            ['AB', false],
            ['DOC-01', false],
        ];
    }

    /** @dataProvider guestPrefixed */
    public function test_ningun_codigo_puede_empezar_con_inv(?string $role): void
    {
        foreach (['INV4821', 'INV1', 'INVADM0001', 'INV202312345'] as $code) {
            $this->assertFalse(SisCodeFormat::isValid($code, $role), "{$code} con " . ($role ?? 'sin rol'));
        }
    }

    public function guestPrefixed(): array
    {
        return [[Role::AUXILIAR], [Role::ADMINISTRADOR], [Role::DOCENTE], [null], [Role::INVITADO]];
    }

    public function test_el_formato_cambia_con_el_año_en_curso(): void
    {
        Carbon::setTestNow('2030-01-05');

        $this->assertTrue(SisCodeFormat::isValid('202912345', Role::AUXILIAR));
        $this->assertTrue(SisCodeFormat::isValid('203012345', Role::AUXILIAR));
        $this->assertFalse(SisCodeFormat::isValid('203112345', Role::AUXILIAR));
    }
}
