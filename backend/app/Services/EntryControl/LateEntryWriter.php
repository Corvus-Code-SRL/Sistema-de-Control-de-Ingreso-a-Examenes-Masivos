<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\StudentExam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Escribe el ingreso CON_RETRASO de HU-037 en examen_estudiante. */
class LateEntryWriter
{
    /**
     * Si el estudiante ya tiene fila sin ingreso, la completa; si no, la crea con el grupo del examen
     * en que está inscrito. Devuelve la hora local guardada.
     *
     * @param array{estudiante: array{id_estudiante: int}, grupo: array{id_grupo: int, num_grupo: string}} $record
     */
    public function write(
        Exam $exam,
        array $record,
        ?object $existing,
        int $roomId,
        ?int $plannedRoomId,
        string $controllerId,
        Carbon $entryAt,
        Carbon $registeredAt
    ): string {
        $table = (new StudentExam())->getTable();
        $entryTime = $entryAt->copy()->setTimezone(config('sciem.zona_horaria'))->format('H:i:s');
        $attributes = [
            'estado_ingreso' => StudentExam::CON_RETRASO,
            'hora_ingreso' => $entryTime,
            'id_ambiente' => $roomId,
            'id_ambiente_planificado' => $plannedRoomId,
            'id_usuario_controlador' => $controllerId,
            'registrado_en' => $registeredAt->copy()->setTimezone('UTC')->format('Y-m-d H:i:sP'),
        ];
        $studentId = (int) $record['estudiante']['id_estudiante'];

        if ($existing === null) {
            DB::table($table)->insert($attributes + [
                'id_examen' => $exam->id_examen,
                'id_estudiante' => $studentId,
                'id_grupo' => $record['grupo']['id_grupo'],
                'estado_habilitacion' => StudentExam::HABILITADO,
            ]);

            return $entryTime;
        }

        // estado_ingreso es NOT NULL: la fila bloqueada sin ingreso solo puede estar en NO_INGRESO.
        // Si no se actualiza exactamente esa fila, se revierte todo antes de resolver la solicitud.
        $updated = DB::table($table)
            ->where('id_examen', $exam->id_examen)
            ->where('id_estudiante', $studentId)
            ->where('estado_ingreso', StudentExam::NO_INGRESO)
            ->update($attributes);

        if ($updated !== 1) {
            throw new LogicException('No se pudo registrar el ingreso con retraso del estudiante.');
        }

        return $entryTime;
    }
}
