<?php

namespace App\Services\Exams\Importers;

/**
 * Resultado de GET /habilitados (HU-028).
 *
 * Una sola lista `estudiantes[]` con el estado por estudiante. El
 * frontend filtra con los tabs del mockup (Todos, Habilitados, No
 * habilitados).
 *
 * `editable` = (examen.estado === 'PROGRAMADO').
 *
 * Cada estudiante lleva:
 * - id_estudiante
 * - codigo_sis
 * - nombre_completo
 * - id_grupo
 * - nombre_grupo
 * - estado_habilitacion (HABILITADO | CASO ESPECIAL | NO_HABILITADO)
 * - caso_especial (bool)
 * - motivo (null si HABILITADO / CASO ESPECIAL)
 * - detalle (null si el motivo no lo requiere)
 */
final class EligibleListCurrentResult
{
    private bool $editable;
    private string $estado;

    /**
     * @var array<int, array{
     *     id_estudiante: int,
     *     codigo_sis: string,
     *     nombre_completo: string,
     *     estado: string,
     *     id_grupo: int,
     *     nombre_grupo: string,
     *     estado_habilitacion: string,
     *     caso_especial: bool,
     *     motivo: ?string,
     *     detalle: ?string
     * }>
     */
    private array $estudiantes;

    /**
     * @param array<int, array{
     *     id_estudiante: int,
     *     codigo_sis: string,
     *     nombre_completo: string,
     *     estado: string,
     *     id_grupo: int,
     *     nombre_grupo: string,
     *     estado_habilitacion: string,
     *     caso_especial: bool,
     *     motivo: ?string,
     *     detalle: ?string
     * }> $estudiantes
     */
    public function __construct(bool $editable, string $estado, array $estudiantes)
    {
        $this->editable = $editable;
        $this->estado = $estado;
        $this->estudiantes = $estudiantes;
    }

    public function editable(): bool
    {
        return $this->editable;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    /**
     * @return array<int, array{
     *     id_estudiante: int,
     *     codigo_sis: string,
     *     nombre_completo: string,
     *     estado: string,
     *     id_grupo: int,
     *     nombre_grupo: string,
     *     estado_habilitacion: string,
     *     caso_especial: bool,
     *     motivo: ?string,
     *     detalle: ?string
     * }>
     */
    public function estudiantes(): array
    {
        return $this->estudiantes;
    }
}
