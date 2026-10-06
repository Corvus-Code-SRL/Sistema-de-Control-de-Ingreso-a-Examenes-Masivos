<?php

namespace App\Services\Security;

use App\Services\Security\Contracts\SisGateway;

class FakeSisGateway implements SisGateway
{
    /** Datos que el SIS simulado devuelve para las personas que no tienen entrada propia. */
    private const DEFAULT_PERSON = [
        'nombre'   => 'Laura',
        'paterno'  => 'Mendoza',
        'materno'  => 'Rivas',
        'tipo'     => 'Docente',
        'facultad' => 'Facultad de Ciencias y Tecnología',
    ];

    /**
     * Códigos SIS considerados válidos mientras no exista la integración real con el sistema
     * institucional. Una lista vacía significa «datos por defecto»; el resto lleva los propios.
     */
    private array $personas = [
        '202312345' => [],
        '202312346' => [],
        '202312347' => [],
        '201900001' => [],
        // Personal administrativo (código alfanumérico, como el del Administrador de prueba ADM0001).
        'ADM0002' => [
            'nombre'   => 'Camila',
            'paterno'  => 'Ejemplo',
            'materno'  => 'Prueba',
            'tipo'     => 'Funcionario',
            'facultad' => 'Facultad de Ciencias y Tecnología',
        ],
        // Docente (código de 5 dígitos).
        '10611' => [
            'nombre'   => 'Mateo',
            'paterno'  => 'Simulado',
            'materno'  => 'Prueba',
            'tipo'     => 'Docente',
            'facultad' => 'Facultad de Ciencias y Tecnología',
        ],
    ];

    public function estaDisponible(): bool
    {
        return true;
    }

    public function existePersona(string $codSis): bool
    {
        return array_key_exists($codSis, $this->personas);
    }

    /**
     * Cada persona reconocida devuelve sus datos; las que no tienen entrada propia comparten los
     * que antes armaba el controller de verificación.
     */
    public function personData(string $codSis): array
    {
        return $this->personas[$codSis] ?: self::DEFAULT_PERSON;
    }
}
