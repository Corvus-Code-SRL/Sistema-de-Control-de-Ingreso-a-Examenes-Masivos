<?php

namespace App\Services\EntryControl;

use App\Models\User;
use App\Services\RiskCenter\Academic\RiskCenterQueryService;
use Carbon\Carbon;

class VerificationService
{
    private EntryControlSnapshotService $snapshots;
    private IdentificationService $identification;
    private RiskCenterQueryService $riskCenter;

    public function __construct(
        EntryControlSnapshotService $snapshots,
        IdentificationService $identification,
        RiskCenterQueryService $riskCenter
    ) {
        $this->snapshots = $snapshots;
        $this->identification = $identification;
        $this->riskCenter = $riskCenter;
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
            'auxiliar' => null,
            'grupo' => null,
            'ambiente_asignado' => null,
            'antecedentes' => ['total' => 0, 'latest' => null],
            'ingreso_previo' => null,
        ];

        if ($identified['veredicto'] !== 'IDENTIFICADO') {
            $assistant = isset($input['cod_sis'])
                ? $this->identification->assistantBySis($examId, (string) $input['cod_sis'])
                : null;
            if ($assistant !== null) {
                $result['veredicto'] = 'AUXILIAR_EXAMEN';
                $result['motivo'] = 'El SIS corresponde a un auxiliar de este examen. No se registra ingreso de estudiante.';
                $result['auxiliar'] = $assistant;
                return $result;
            }

            $result['motivo'] = 'No se encontró al estudiante.';
            return $result;
        }

        $record = $identified['registro'];
        $result['estudiante'] = $record['estudiante'];
        $result['grupo'] = $record['grupo'];
        $result['ambiente_asignado'] = $record['ambiente_asignado'];
        $risk = $record['antecedentes'] ?? [];
        $result['antecedentes'] = isset($risk['total']) ? $risk : [
            'total' => (int) ($risk['cantidad'] ?? 0),
            'latest' => isset($risk['detalles'][0]) ? [
                'tipo_falta' => $risk['detalles'][0]['tipo'],
                'fecha_registro' => $risk['detalles'][0]['fecha'],
            ] : null,
        ];
        $result['ingreso_previo'] = $record['ingreso_previo'];

        if ($record['grupo'] === null) {
            $summary = $this->riskCenter->summaryForStudent((int) $record['estudiante']['id_estudiante']);
            $latest = $summary['latest'];
            $result['antecedentes'] = [
                'total' => $summary['total'],
                'latest' => $latest === null ? null : [
                    'tipo_falta' => $latest['tipo_falta']['nombre'],
                    'fecha_registro' => Carbon::parse($latest['fecha_registro'])->toDateString(),
                ],
            ];

            return $this->deny($result, 'NO_PERTENECE', 'No figura en los grupos de este examen.');
        }

        if ($record['estado_habilitacion'] === 'NO_HABILITADO') {
            return $this->deny($result, 'NO_HABILITADO', 'El estudiante no está habilitado para este examen.');
        }

        $presentedCi = trim((string) ($input['ci'] ?? ''));
        $registeredCi = trim((string) ($record['estudiante']['ci'] ?? ''));
        if ($presentedCi !== '' && $registeredCi !== '' && $presentedCi !== $registeredCi) {
            return $this->deny(
                $result,
                'CI_NO_COINCIDE',
                'El CI presentado no coincide con el registrado para este estudiante.'
            );
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
