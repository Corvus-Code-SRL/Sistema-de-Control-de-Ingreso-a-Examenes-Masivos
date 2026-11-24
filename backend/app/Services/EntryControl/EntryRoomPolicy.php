<?php

namespace App\Services\EntryControl;

use App\Exceptions\EntryControl\EntryRoomWarningException;
use App\Services\Exams\Distribution\DistributionSuspensionFlag;
use App\Services\Exams\Distribution\RoomDistributionReader;
use App\Services\Security\AuditLogService;
use App\Support\Exams\EntryRoomWarning;

/**
 * Regla común de ambiente para HU-032 y HU-037: la distribución y la capacidad avisan, nunca rechazan.
 * El excepcional (HU-023) no está en la distribución: su ambiente asignado es el que se eligió al
 * registrarlo y, si ingresa por otro, recibe el mismo aviso.
 *
 * @phpstan-type RoomWarning array{tipo:string, mensaje:string, ambiente_asignado?:array{id_ambiente:int,
 *     nro_aula:string}, ocupados?:int, capacidad?:int}
 * @phpstan-type RoomResolution array{avisos:list<RoomWarning>, id_ambiente_planificado:int|null}
 */
class EntryRoomPolicy
{
    public const AUDIT_ACTION = 'INGRESO_CON_AVISO_AMBIENTE';

    public function __construct(
        private RoomDistributionReader $distribution,
        private DistributionSuspensionFlag $suspension,
        private RoomOccupancyService $occupancy,
        private AuditLogService $audit
    ) {
    }

    /**
     * Agrega el aviso de ambiente distinto sin consultar PostgreSQL: verificar es el camino crítico del
     * ingreso masivo. La capacidad se comprueba en resolve(), con el examen bloqueado.
     *
     * @param array{autorizado:bool, ambiente_asignado:array{id_ambiente:int, nro_aula:string}|null,
     *     es_excepcional?:bool} $verdict
     * @return array{autorizado:bool, ambiente_asignado:array{id_ambiente:int, nro_aula:string}|null,
     *     es_excepcional?:bool, avisos_ambiente:list<RoomWarning>}
     */
    public function decorate(int $examId, array $verdict, int $roomId): array
    {
        if ($this->suspension->isMarked($examId)) {
            $verdict['ambiente_asignado'] = null;
        }

        $verdict['avisos_ambiente'] = $verdict['autorizado']
            ? $this->outOfRoomWarnings($verdict['ambiente_asignado'], $roomId)
            : [];

        return $verdict;
    }

    /**
     * Recalcula desde PostgreSQL con el examen bloqueado y exige confirmar si hay avisos.
     *
     * @param array{ambiente_asignado:array{id_ambiente:int, nro_aula:string}|null, es_excepcional?:bool} $record
     * @return RoomResolution
     * @throws EntryRoomWarningException
     */
    public function resolve(int $examId, array $record, int $roomId, bool $confirmed): array
    {
        $assigned = $this->distribution->isSuspended($examId) ? null : $record['ambiente_asignado'];
        $warnings = array_merge(
            $this->outOfRoomWarnings($assigned, $roomId),
            $this->fullRoomWarnings($examId, $roomId)
        );

        if ($warnings !== [] && ! $confirmed) {
            throw new EntryRoomWarningException($warnings);
        }

        $outOfRoom = array_values(array_filter(
            $warnings,
            fn (array $warning): bool => $warning['tipo'] === EntryRoomWarning::OUT_OF_ROOM
        ));

        return [
            'avisos' => $warnings,
            'id_ambiente_planificado' => $outOfRoom === [] ? null : $outOfRoom[0]['ambiente_asignado']['id_ambiente'],
        ];
    }

    /** @param RoomResolution $resolution */
    public function audit(int $examId, int $studentId, int $roomId, array $resolution, string $actorId): void
    {
        if ($resolution['avisos'] === []) {
            return;
        }

        $this->audit->registrar(self::AUDIT_ACTION, 'examen_estudiante', null, [
            'id_examen' => $examId,
            'id_estudiante' => $studentId,
            'id_ambiente_asignado' => $resolution['id_ambiente_planificado'],
            'id_ambiente_real' => $roomId,
            'avisos' => array_column($resolution['avisos'], 'tipo'),
        ], $actorId);
    }

    /**
     * @param array{id_ambiente:int, nro_aula:string}|null $assigned
     * @return list<RoomWarning>
     */
    private function outOfRoomWarnings(?array $assigned, int $roomId): array
    {
        if ($assigned === null || (int) $assigned['id_ambiente'] === $roomId) {
            return [];
        }

        return [[
            'tipo' => EntryRoomWarning::OUT_OF_ROOM,
            'mensaje' => sprintf(EntryRoomWarning::OUT_OF_ROOM_MESSAGE, $assigned['nro_aula']),
            'ambiente_asignado' => [
                'id_ambiente' => (int) $assigned['id_ambiente'],
                'nro_aula' => (string) $assigned['nro_aula'],
            ],
        ]];
    }

    /** @return list<RoomWarning> */
    private function fullRoomWarnings(int $examId, int $roomId): array
    {
        $room = $this->occupancy->forRoom($examId, $roomId);

        if ($room === null || $room['ocupados'] < $room['capacidad']) {
            return [];
        }

        return [[
            'tipo' => EntryRoomWarning::ROOM_FULL,
            'mensaje' => sprintf(
                EntryRoomWarning::ROOM_FULL_MESSAGE,
                $room['nro_aula'],
                $room['ocupados'],
                $room['capacidad']
            ),
            'ocupados' => $room['ocupados'],
            'capacidad' => $room['capacidad'],
        ]];
    }
}
