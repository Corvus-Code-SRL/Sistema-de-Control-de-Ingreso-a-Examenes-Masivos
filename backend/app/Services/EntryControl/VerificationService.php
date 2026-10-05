<?php

namespace App\Services\EntryControl;

use App\Models\User;

class VerificationService
{
    private EntryControlSnapshotService $snapshots;
    private IdentificationService $identification;

    public function __construct(
        EntryControlSnapshotService $snapshots,
        IdentificationService $identification
    ) {
        $this->snapshots = $snapshots;
        $this->identification = $identification;
    }

    public function verify(int $examId, array $input, User $actor): array
    {
        $roomId = $this->snapshots->roomFor($examId, $actor, (int) $input['id_ambiente']);
        $identified = $this->identification->identify($examId, $input);
        $result = [
            'veredicto' => $identified['veredicto'],
            'motivo' => '',
            'autorizado' => false,
            'estudiante' => null,
            'grupo' => null,
            'ambiente_asignado' => null,
            'antecedentes' => ['tiene_antecedentes' => false, 'cantidad' => 0, 'resumen' => null],
            'ingreso_previo' => null,
        ];

        if ($identified['veredicto'] !== 'IDENTIFICADO') {
            $result['motivo'] = 'No se encontró al estudiante.';
            return $result;
        }

        $record = $identified['registro'];
        $result['estudiante'] = $record['estudiante'];
        $result['grupo'] = $record['grupo'];
        $result['ambiente_asignado'] = $record['ambiente_asignado'];
        $result['antecedentes'] = $record['antecedentes'];
        $result['ingreso_previo'] = $record['ingreso_previo'];

        if ($record['grupo'] === null) {
            return $this->deny($result, 'NO_PERTENECE', 'No figura en los grupos de este examen.');
        }

        if ($record['estado_habilitacion'] === 'NO_HABILITADO') {
            return $this->deny($result, 'NO_HABILITADO', 'El estudiante no está habilitado para este examen.');
        }

        if ($record['ingreso_previo'] !== null) {
            return $this->deny($result, 'DUPLICADO', 'El estudiante ya registró su ingreso a este examen.');
        }

        if ($record['ambiente_asignado'] === null) {
            return $this->deny($result, 'AULA_NO_ASIGNADA', 'No se pudo determinar un ambiente para el estudiante.');
        }

        if ((int) $record['ambiente_asignado']['id_ambiente'] !== $roomId) {
            return $this->deny(
                $result,
                'AULA_INCORRECTA',
                'Debe dirigirse al ambiente ' . $record['ambiente_asignado']['nro_aula'] . '.'
            );
        }

        $result['veredicto'] = 'AUTORIZADO';
        $result['motivo'] = 'Inscrito en un grupo del examen y asignado a este ambiente.';
        $result['autorizado'] = true;

        return $result;
    }

    private function deny(array $result, string $code, string $message): array
    {
        $result['veredicto'] = $code;
        $result['motivo'] = $message;
        return $result;
    }
}
