<?php

namespace Tests\Feature;

use App\Services\Security\AuditLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rechaza_una_operacion_de_auditoria_no_registrada(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            "La operación de auditoría 'OPERACION_INEXISTENTE' no está registrada."
        );

        app(AuditLogService::class)->registrar(
            'OPERACION_INEXISTENTE',
            'examen',
            null,
            null,
            'usuario-prueba'
        );
    }
}
