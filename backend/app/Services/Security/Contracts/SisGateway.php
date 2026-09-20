<?php

namespace App\Services\Security\Contracts;

interface SisGateway
{
    /** ¿El servicio institucional responde en este momento? (CA 10) */
    public function estaDisponible(): bool;

    /** ¿El código SIS corresponde a una persona reconocida? (CA 2, 3) */
    public function existePersona(string $codSis): bool;
}
