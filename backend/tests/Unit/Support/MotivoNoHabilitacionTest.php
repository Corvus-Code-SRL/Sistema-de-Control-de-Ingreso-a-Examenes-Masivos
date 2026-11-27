<?php

namespace Tests\Unit\Support;

use App\Support\MotivoNoHabilitacion;
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
    }
}
