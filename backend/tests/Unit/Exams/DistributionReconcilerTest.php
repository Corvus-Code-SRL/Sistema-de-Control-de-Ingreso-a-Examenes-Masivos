<?php

namespace Tests\Unit\Exams;

use App\Services\Exams\Distribution\DistributionReconciler;
use PHPUnit\Framework\TestCase;

class DistributionReconcilerTest extends TestCase
{
    public function test_conserva_asignados_y_deja_los_nuevos_sin_ambiente(): void
    {
        $result = (new DistributionReconciler())->reconcile([1, 2, 3], [
            ['id_ambiente' => 10, 'capacidad' => 30],
            ['id_ambiente' => 20, 'capacidad' => 30],
        ], [
            ['id_estudiante' => 1, 'id_ambiente' => 20],
            ['id_estudiante' => 2, 'id_ambiente' => 10],
        ]);

        $this->assertSame([1 => 20, 2 => 10, 3 => null], $result);
    }

    public function test_desvincula_no_habilitados_y_ambientes_retirados_del_examen(): void
    {
        $result = (new DistributionReconciler())->reconcile([1, 3], [
            ['id_ambiente' => 10, 'capacidad' => 1],
        ], [
            ['id_estudiante' => 2, 'id_ambiente' => 10],
            ['id_estudiante' => 1, 'id_ambiente' => 10],
            ['id_estudiante' => 3, 'id_ambiente' => 20],
        ]);

        $this->assertSame([1 => 10, 3 => null], $result);
    }

    public function test_capacidad_reducida_conserva_el_orden_de_la_nomina_sin_sobrepasarla(): void
    {
        $result = (new DistributionReconciler())->reconcile([3, 1, 2], [
            ['id_ambiente' => 10, 'capacidad' => 1],
        ], [
            ['id_estudiante' => 1, 'id_ambiente' => 10],
            ['id_estudiante' => 2, 'id_ambiente' => 10],
            ['id_estudiante' => 3, 'id_ambiente' => 10],
        ]);

        $this->assertSame([3 => 10, 1 => null, 2 => null], $result);
    }

    public function test_sin_distribucion_o_sin_ambientes_deja_todos_sin_asignar(): void
    {
        $reconciler = new DistributionReconciler();

        $this->assertSame([1 => null], $reconciler->reconcile([1], [['id_ambiente' => 1, 'capacidad' => 2]], []));
        $this->assertSame([1 => null], $reconciler->reconcile([1], [], [
            ['id_estudiante' => 1, 'id_ambiente' => 1],
        ]));
        $this->assertSame([], $reconciler->reconcile([], [], []));
    }
}
