<?php

namespace Tests\Unit\Support;

use App\Support\MotivoNoHabilitacion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MotivoNoHabilitacionTest extends TestCase
{
    public function test_from_observation_con_otro_y_detalle(): void
    {
        $this->assertSame(
            [
                'motivo' => MotivoNoHabilitacion::OTRO,
                'detalle' => 'Documento pendiente',
            ],
            MotivoNoHabilitacion::fromObservation('OTRO: Documento pendiente')
        );
    }

    public function test_from_observation_con_otro_sin_detalle(): void
    {
        $this->assertSame(
            [
                'motivo' => MotivoNoHabilitacion::OTRO,
                'detalle' => null,
            ],
            MotivoNoHabilitacion::fromObservation('OTRO')
        );
    }

    public function test_from_observation_con_asistencia_insuficiente(): void
    {
        $this->assertSame(
            [
                'motivo' => MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
                'detalle' => null,
            ],
            MotivoNoHabilitacion::fromObservation('ASISTENCIA_INSUFICIENTE')
        );
    }

    public function test_from_observation_con_observacion_ajena_devuelve_null(): void
    {
        $this->assertNull(
            MotivoNoHabilitacion::fromObservation('Reporte creado por HU-045')
        );
    }

    public function test_from_observation_null_devuelve_null(): void
    {
        $this->assertNull(MotivoNoHabilitacion::fromObservation(null));
    }

    public function test_to_observation_otro_con_detalle_que_empieza_con_dos_puntos_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El detalle no puede empezar con ":".');

        MotivoNoHabilitacion::toObservation(MotivoNoHabilitacion::OTRO, ': algo');
    }

    public function test_to_observation_otro_sin_detalle_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El motivo OTRO exige un detalle.');

        MotivoNoHabilitacion::toObservation(MotivoNoHabilitacion::OTRO, null);
    }

    public function test_to_observation_motivo_invalido_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("El motivo 'NO_EXISTE' no es válido.");

        MotivoNoHabilitacion::toObservation('NO_EXISTE', null);
    }

    public function test_to_observation_no_concatena_detalle_si_el_motivo_no_lo_requiere(): void
    {
        $this->assertSame(
            MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
            MotivoNoHabilitacion::toObservation(
                MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE,
                'detalle que no aplica'
            )
        );
    }

    public function test_to_observation_otro_con_detalle_valido(): void
    {
        $this->assertSame(
            'OTRO: Documento pendiente',
            MotivoNoHabilitacion::toObservation(
                MotivoNoHabilitacion::OTRO,
                'Documento pendiente'
            )
        );
    }

    public function test_label_devuelve_la_etiqueta_legible(): void
    {
        $this->assertSame('Asistencia insuficiente', MotivoNoHabilitacion::label(MotivoNoHabilitacion::ASISTENCIA_INSUFICIENTE));
        $this->assertSame('No entregó el trabajo final', MotivoNoHabilitacion::label(MotivoNoHabilitacion::NO_ENTREGO_TRABAJO_FINAL));
        $this->assertSame('No cumple prerrequisito', MotivoNoHabilitacion::label(MotivoNoHabilitacion::NO_CUMPLE_PRERREQUISITO));
        $this->assertSame('Otro', MotivoNoHabilitacion::label(MotivoNoHabilitacion::OTRO));
    }

    public function test_label_de_un_codigo_desconocido_devuelve_el_codigo(): void
    {
        $this->assertSame('NO_EXISTE', MotivoNoHabilitacion::label('NO_EXISTE'));
    }

    public function test_el_motivo_fijo_del_archivo_tiene_etiqueta_y_se_deserializa(): void
    {
        $this->assertSame(
            'No figura en la lista cargada',
            MotivoNoHabilitacion::label(MotivoNoHabilitacion::NO_FIGURA_EN_LISTA)
        );
        $this->assertSame(
            ['motivo' => MotivoNoHabilitacion::NO_FIGURA_EN_LISTA, 'detalle' => null],
            MotivoNoHabilitacion::fromObservation(MotivoNoHabilitacion::NO_FIGURA_EN_LISTA)
        );
    }

    public function test_el_motivo_fijo_no_es_elegible_a_mano(): void
    {
        $this->assertNotContains(MotivoNoHabilitacion::NO_FIGURA_EN_LISTA, MotivoNoHabilitacion::all());
        $this->assertFalse(MotivoNoHabilitacion::isValid(MotivoNoHabilitacion::NO_FIGURA_EN_LISTA));
    }
}
