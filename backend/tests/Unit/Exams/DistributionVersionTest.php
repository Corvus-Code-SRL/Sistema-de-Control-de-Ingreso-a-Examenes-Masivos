<?php

namespace Tests\Unit\Exams;

use App\Services\Exams\Distribution\DistributionVersion;
use PHPUnit\Framework\TestCase;

class DistributionVersionTest extends TestCase
{
    public function test_no_depende_del_orden_de_filas_o_columnas(): void
    {
        $rows = [$this->row(1, 10), $this->row(2, 20)];
        $reordered = array_map(fn (array $row): array => array_reverse($row, true), array_reverse($rows));

        $this->assertSame(DistributionVersion::fromRows($rows), DistributionVersion::fromRows($reordered));
    }

    public function test_cambia_al_mover_agregar_o_quitar_un_estudiante(): void
    {
        $version = DistributionVersion::fromRows([$this->row(1, 10)]);

        $this->assertNotSame($version, DistributionVersion::fromRows([$this->row(1, 20)]));
        $this->assertNotSame($version, DistributionVersion::fromRows([$this->row(1, 10), $this->row(2, 20)]));
        $this->assertNotSame($version, DistributionVersion::fromRows([]));
    }

    public function test_un_nuevo_guardado_invalida_la_version_aunque_restaure_los_mismos_ambientes(): void
    {
        $row = $this->row(1, 10);
        $version = DistributionVersion::fromRows([$row]);
        $row['asignado_en'] = '2026-11-18 11:00:00+00';

        $this->assertNotSame($version, DistributionVersion::fromRows([$row]));
        $row = $this->row(1, 10);
        $row['id_usuario_asignador'] = '22222222-2222-4222-8222-222222222222';
        $this->assertNotSame($version, DistributionVersion::fromRows([$row]));
    }

    public function test_sin_filas_tiene_una_version_inicial_estable(): void
    {
        $this->assertSame(sha1('[]'), DistributionVersion::fromRows([]));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', DistributionVersion::fromRows([]));
    }

    public function test_distingue_microsegundos_con_el_mismo_estudiante_ambiente_y_autor(): void
    {
        $row = $this->row(1, 10);
        $row['asignado_en'] = '2026-11-17 10:00:00.100001+00';
        $version = DistributionVersion::fromRows([$row]);
        $row['asignado_en'] = '2026-11-17 10:00:00.100002+00';

        $this->assertNotSame($version, DistributionVersion::fromRows([$row]));
    }

    /** @return array{id_examen:int, id_estudiante:int, id_ambiente:int,
     *     id_usuario_asignador:string, asignado_en:string} */
    private function row(int $studentId, int $roomId): array
    {
        return [
            'id_examen' => 1,
            'id_estudiante' => $studentId,
            'id_ambiente' => $roomId,
            'id_usuario_asignador' => '11111111-1111-4111-8111-111111111111',
            'asignado_en' => '2026-11-18 10:00:00+00',
        ];
    }
}
