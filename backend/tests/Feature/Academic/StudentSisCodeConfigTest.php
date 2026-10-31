<?php

namespace Tests\Feature\Academic;

use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterRowValidator;
use Tests\TestCase;

/**
 * Las cotas del código SIS de estudiante salen de config/sciem.php, no del código.
 */
class StudentSisCodeConfigTest extends TestCase
{
    public function test_las_cotas_por_defecto_son_de_8_a_12_digitos(): void
    {
        $this->assertSame(8, config('sciem.estudiante_cod_sis.min_length'));
        $this->assertSame(12, config('sciem.estudiante_cod_sis.max_length'));
    }

    public function test_el_validador_resuelto_por_el_contenedor_lee_las_cotas_de_la_configuracion(): void
    {
        $row = new StudentRosterRow(2, '1234567', 'PEREZ ROJAS', 'ANA');

        $this->assertSame(
            ['sis_code_invalid_length'],
            app(StudentRosterRowValidator::class)->validate($row)
        );

        config()->set('sciem.estudiante_cod_sis.min_length', 7);

        $this->assertSame([], app(StudentRosterRowValidator::class)->validate($row));
    }

    public function test_un_cambio_de_la_cota_maxima_se_aplica_sin_tocar_el_codigo(): void
    {
        $row = new StudentRosterRow(2, '1234567890123', 'PEREZ ROJAS', 'ANA');

        $this->assertContains(
            'sis_code_invalid_length',
            app(StudentRosterRowValidator::class)->validate($row)
        );

        config()->set('sciem.estudiante_cod_sis.max_length', 13);

        $this->assertSame([], app(StudentRosterRowValidator::class)->validate($row));
    }
}
