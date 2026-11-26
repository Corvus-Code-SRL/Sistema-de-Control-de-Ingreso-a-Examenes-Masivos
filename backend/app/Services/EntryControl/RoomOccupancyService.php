<?php

namespace App\Services\EntryControl;

use App\Models\StudentExam;
use Illuminate\Support\Facades\DB;

/** Ocupación real de un ambiente: quienes ingresaron por él y no fueron retirados. */
class RoomOccupancyService
{
    private const PRESENT_STATES = [StudentExam::INGRESO, StudentExam::CON_RETRASO];

    /** @return array{nro_aula:string, capacidad:int, ocupados:int}|null */
    public function forRoom(int $examId, int $roomId): ?array
    {
        $room = DB::table('ambiente')->where('id_ambiente', $roomId)->first(['nro_aula', 'capacidad']);

        if ($room === null) {
            return null;
        }

        return [
            'nro_aula' => (string) $room->nro_aula,
            'capacidad' => (int) $room->capacidad,
            'ocupados' => DB::table('examen_estudiante')
                ->where('id_examen', $examId)
                ->where('id_ambiente', $roomId)
                ->whereIn('estado_ingreso', self::PRESENT_STATES)
                ->whereNull('expulsado_en')
                ->count(),
        ];
    }
}
