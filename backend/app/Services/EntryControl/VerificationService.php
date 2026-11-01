<?php

namespace App\Services\EntryControl;

use App\Models\User;
use App\Services\RiskCenter\Academic\RiskCenterQueryService;
use Carbon\Carbon;

/**
 * Verificación de un estudiante en el control de ingreso. Las reglas viven en evaluate(),
 * que no consulta nada: el control normal (HU-032) y el ingreso con retraso (HU-037)
 * arman el registro cada uno desde su fuente y lo evalúan igual.
 */
class VerificationService
{
    public const AUTHORIZED = 'AUTORIZADO';
    public const NOT_FOUND = 'NO_ENCONTRADO';
    public const EXAM_ASSISTANT = 'AUXILIAR_EXAMEN';
    public const NOT_IN_EXAM = 'NO_PERTENECE';
    public const NOT_ENABLED = 'NO_HABILITADO';
    public const CI_MISMATCH = 'CI_NO_COINCIDE';
    public const DUPLICATE = 'DUPLICADO';
    public const ROOM_NOT_ASSIGNED = 'AULA_NO_ASIGNADA';
    public const WRONG_ROOM = 'AULA_INCORRECTA';

    public const NOT_FOUND_MESSAGE = 'Estudiante no encontrado';
    public const EXAM_ASSISTANT_MESSAGE =
        'El SIS corresponde a un auxiliar de este examen. No se registra ingreso de estudiante.';
    public const NOT_IN_EXAM_MESSAGE = 'No pertenece a los grupos de este examen';
    public const NOT_ENABLED_MESSAGE = 'El estudiante no está habilitado para este examen.';
    public const CI_MISMATCH_MESSAGE = 'El CI no coincide con el registrado';
    public const ROOM_NOT_ASSIGNED_MESSAGE = 'No se pudo determinar un ambiente para el estudiante.';
    public const AUTHORIZED_MESSAGE = 'Inscrito en un grupo del examen y asignado a este ambiente.';

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

        if ($identified['veredicto'] !== IdentificationService::IDENTIFIED) {
            $result = $this->emptyResult($identified['veredicto']);
            $assistant = isset($input['cod_sis'])
                ? $this->identification->assistantBySis($examId, (string) $input['cod_sis'])
                : null;
            if ($assistant !== null) {
                $result['veredicto'] = self::EXAM_ASSISTANT;
                $result['motivo'] = self::EXAM_ASSISTANT_MESSAGE;
                $result['auxiliar'] = $assistant;
                return $result;
            }

            $result['motivo'] = self::NOT_FOUND_MESSAGE;
            return $result;
        }

        return $this->evaluate($this->withBackground($identified['registro']), $input, $roomId);
    }

    /**
     * Al estudiante ajeno al examen el snapshot no le precalcula antecedentes: se consultan
     * aquí para mostrarle la alerta al controlador.
     *
     * @param array{
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     nombre?: string, apellido_paterno?: string|null, apellido_materno?: string|null,
     *     grupo: array{id_grupo: int, num_grupo: string}|null,
     *     estado_habilitacion: string|null,
     *     ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *     antecedentes?: array{total: int, latest: array{tipo_falta: string, fecha_registro: string}|null},
     *     ingreso_previo: array{registrado_en: string|null, hora_ingreso: string|null,
     *         id_ambiente: int|null, nro_aula: string|null, controlador: string}|null
     * } $record
     * @return array{
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     nombre?: string, apellido_paterno?: string|null, apellido_materno?: string|null,
     *     grupo: array{id_grupo: int, num_grupo: string}|null,
     *     estado_habilitacion: string|null,
     *     ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *     antecedentes?: array{total: int, latest: array{tipo_falta: string, fecha_registro: string}|null},
     *     ingreso_previo: array{registrado_en: string|null, hora_ingreso: string|null,
     *         id_ambiente: int|null, nro_aula: string|null, controlador: string}|null
     * }
     */
    public function withBackground(array $record): array
    {
        if ($record['grupo'] !== null) {
            return $record;
        }

        $summary = $this->riskCenter->summaryForStudent((int) $record['estudiante']['id_estudiante']);
        $latest = $summary['latest'];
        $record['antecedentes'] = [
            'total' => $summary['total'],
            'latest' => $latest === null ? null : [
                'tipo_falta' => $latest['tipo_falta']['nombre'],
                'fecha_registro' => Carbon::parse($latest['fecha_registro'])->toDateString(),
            ],
        ];

        return $record;
    }

    /**
     * Aplica las reglas de ingreso a un registro ya identificado, sin consultar nada.
     *
     * Con $controlRoomId se exige que el estudiante corresponda a ese ambiente; sin él
     * (el docente que elige el ambiente en el ingreso con retraso) no se compara.
     *
     * @param array{
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     grupo: array{id_grupo: int, num_grupo: string}|null,
     *     estado_habilitacion: string|null,
     *     ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *     antecedentes?: array{total?: int, latest?: array{tipo_falta: string, fecha_registro: string}|null,
     *         cantidad?: int, detalles?: array<int, array{tipo: string, fecha: string}>},
     *     ingreso_previo: array{registrado_en: string|null, hora_ingreso: string|null,
     *         id_ambiente: int|null, nro_aula: string|null, controlador: string}|null
     * } $record
     * @param array{ci?: string|null} $input
     * @return array{
     *     veredicto: string, motivo: string, autorizado: bool,
     *     estudiante: array{id_estudiante: int, cod_sis: string, ci: string|null, ci_pendiente: bool,
     *         nombre_completo: string, carrera: string|null, foto_url: string|null},
     *     auxiliar: null,
     *     grupo: array{id_grupo: int, num_grupo: string}|null,
     *     ambiente_asignado: array{id_ambiente: int, nro_aula: string}|null,
     *     antecedentes: array{total: int, latest: array{tipo_falta: string, fecha_registro: string}|null},
     *     ingreso_previo: array{registrado_en: string|null, hora_ingreso: string|null,
     *         id_ambiente: int|null, nro_aula: string|null, controlador: string}|null
     * }
     */
    public function evaluate(array $record, array $input, ?int $controlRoomId): array
    {
        $result = $this->emptyResult(IdentificationService::IDENTIFIED);
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
            return $this->deny($result, self::NOT_IN_EXAM, self::NOT_IN_EXAM_MESSAGE);
        }

        if ($record['estado_habilitacion'] === 'NO_HABILITADO') {
            return $this->deny($result, self::NOT_ENABLED, self::NOT_ENABLED_MESSAGE);
        }

        $presentedCi = trim((string) ($input['ci'] ?? ''));
        $registeredCi = trim((string) ($record['estudiante']['ci'] ?? ''));
        if ($presentedCi !== '' && $registeredCi !== '' && $presentedCi !== $registeredCi) {
            return $this->deny($result, self::CI_MISMATCH, self::CI_MISMATCH_MESSAGE);
        }

        if ($record['ingreso_previo'] !== null) {
            return $this->deny(
                $result,
                self::DUPLICATE,
                'Ya ingresó a las ' . substr((string) $record['ingreso_previo']['hora_ingreso'], 0, 5)
                    . ' por el ' . $record['ingreso_previo']['nro_aula']
            );
        }

        if ($record['ambiente_asignado'] === null) {
            return $this->deny($result, self::ROOM_NOT_ASSIGNED, self::ROOM_NOT_ASSIGNED_MESSAGE);
        }

        if ($controlRoomId !== null && (int) $record['ambiente_asignado']['id_ambiente'] !== $controlRoomId) {
            return $this->deny(
                $result,
                self::WRONG_ROOM,
                'Corresponde al ambiente ' . $record['ambiente_asignado']['nro_aula']
            );
        }

        $result['veredicto'] = self::AUTHORIZED;
        $result['motivo'] = self::AUTHORIZED_MESSAGE;
        $result['autorizado'] = true;

        return $result;
    }

    /**
     * Resultado para un SIS que no corresponde a ningún estudiante, con el mismo formato de verify().
     *
     * @return array{veredicto: string, motivo: string, autorizado: bool, estudiante: null, auxiliar: null,
     *     grupo: null, ambiente_asignado: null, antecedentes: array{total: int, latest: null}, ingreso_previo: null}
     */
    public function notFoundResult(): array
    {
        $result = $this->emptyResult(self::NOT_FOUND);
        $result['motivo'] = self::NOT_FOUND_MESSAGE;

        return $result;
    }

    private function emptyResult(string $verdict): array
    {
        return [
            'veredicto' => $verdict,
            'motivo' => '',
            'autorizado' => false,
            'estudiante' => null,
            'auxiliar' => null,
            'grupo' => null,
            'ambiente_asignado' => null,
            'antecedentes' => ['total' => 0, 'latest' => null],
            'ingreso_previo' => null,
        ];
    }

    private function deny(array $result, string $code, string $message): array
    {
        $result['veredicto'] = $code;
        $result['motivo'] = $message;
        return $result;
    }
}
