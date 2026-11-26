<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/** Guarda una distribución de HU-031 directamente, para pruebas de quienes la consumen. */
trait PlansExamRooms
{
    /** @param array<int, int> $roomByStudent */
    protected function planRooms(int $examId, array $roomByStudent): void
    {
        foreach ($roomByStudent as $studentId => $roomId) {
            DB::table('distribucion_ambiente')->insert([
                'id_examen' => $examId,
                'id_estudiante' => $studentId,
                'id_ambiente' => $roomId,
                'id_usuario_asignador' => $this->docenteId,
                'asignado_en' => '2026-01-01 00:00:00.000000+00',
            ]);
        }
    }
}
