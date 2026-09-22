<?php

namespace App\Services\Security\Contracts;

interface SisGateway
{
    /** ¿El servicio institucional responde en este momento? (CA 10) */
    public function estaDisponible(): bool;

    /** ¿El código SIS corresponde a una persona reconocida? (CA 2, 3) */
    public function existePersona(string $codSis): bool;

    /**
     * Datos de la persona según el SIS: nombre, paterno, materno, tipo y facultad.
     * Solo se consulta después de confirmar que existe.
     */
    public function personData(string $codSis): array;
}
