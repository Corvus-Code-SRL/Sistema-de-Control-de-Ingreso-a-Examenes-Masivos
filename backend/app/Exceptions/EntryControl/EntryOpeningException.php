<?php

namespace App\Exceptions\EntryControl;

use DomainException;

/** Motivo de negocio que puede mostrarse cuando no se logra abrir el ingreso. */
class EntryOpeningException extends DomainException
{
    public const MISSING_ROSTER = 'SIN_NOMINA';
    public const MISSING_ROOMS = 'SIN_AMBIENTES';
    public const INSUFFICIENT_CAPACITY = 'CAPACIDAD_INSUFICIENTE';

    private string $reason;

    public function __construct(string $reason, string $message)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
