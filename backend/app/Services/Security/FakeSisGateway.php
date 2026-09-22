<?php

namespace App\Services\Security;

use App\Services\Security\Contracts\SisGateway;

class FakeSisGateway implements SisGateway
{
    /**
     * Códigos SIS considerados válidos mientras no exista
     * la integración real con el sistema institucional.
     */
    private array $codigosValidos = [
        '202312345',
        '202312346',
        '202312347',
        '201900001',
    ];

    public function estaDisponible(): bool
    {
        return true;
    }

    public function existePersona(string $codSis): bool
    {
        return in_array($codSis, $this->codigosValidos, true);
    }

    /**
     * Sin integración real, toda persona reconocida devuelve los mismos datos:
     * son los que antes armaba el controller de verificación.
     */
    public function personData(string $codSis): array
    {
        return [
            'nombre'   => 'Laura',
            'paterno'  => 'Mendoza',
            'materno'  => 'Rivas',
            'tipo'     => 'Docente',
            'facultad' => 'Facultad de Ciencias y Tecnología',
        ];
    }
}
