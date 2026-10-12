<?php

namespace App\Services\Security;

use App\Exceptions\Security\DuplicateAccountException;
use App\Exceptions\Security\SisNoDisponibleException;
use App\Exceptions\Security\SisNoValidoException;
use App\Models\User;
use App\Services\Security\Contracts\SisGateway;
use App\Support\SisCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    private SisGateway $sis;
    private AuditLogService $auditoria;

    public function __construct(SisGateway $sis, AuditLogService $auditoria)
    {
        $this->sis = $sis;
        $this->auditoria = $auditoria;
    }

    /**
     * Registra una cuenta de usuario en SCIEM.
     *
     * La cuenta se crea SIN rol asignado (CA 5): el rol se asigna después
     * (HU-004), en el mismo flujo del registro o desde Cuentas.
     *
     * La contraseña temporal solo se guarda hasheada y la cuenta queda obligada a
     * cambiarla en su primer ingreso.
     */
    public function registrar(array $datos, string $actorId): User
    {
        $datos['cod_sis'] = SisCode::normalize($datos['cod_sis']);

        $this->validarContraSis($datos['cod_sis']);

        return DB::transaction(function () use ($datos, $actorId) {
            $usuario = User::create([
                'nombre'           => $datos['nombre'],
                'apellido_paterno' => $datos['apellido_paterno'],
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'correo'           => $datos['correo'],
                'cod_sis'          => $datos['cod_sis'],
                'contrasenia'      => Hash::make($datos['contrasenia_temporal']),
                'debe_cambiar_contrasenia' => true,
                'estado'           => User::ESTADO_ACTIVO,
            ]);

            // CA 5: no se inserta nada en usuario_rol. La cuenta nace sin rol.

            // CA 6: trazabilidad
            $this->auditoria->registrar(
                'CREAR',
                'usuario',
                null,
                $usuario->toArray(),   // AuditLogService filtra 'contrasenia'
                $actorId
            );

            return $usuario;
        });
    }

    /**
     * Paso previo al registro: confirma que el código SIS puede recibir una cuenta
     * y devuelve los datos de la persona según el SIS.
     *
     * El duplicado se revisa antes que la existencia en el SIS, para avisar
     * de la cuenta existente aunque el código también sea válido.
     */
    public function verifySisCode(string $codSis): array
    {
        $codSis = SisCode::normalize($codSis);

        $this->assertSisAvailable();

        $existing = User::where('cod_sis', $codSis)->first();

        if ($existing !== null) {
            throw new DuplicateAccountException($existing->nombre_completo);   // -> 422
        }

        if (! $this->sis->existePersona($codSis)) {
            throw new SisNoValidoException();       // -> 422
        }

        return $this->sis->personData($codSis);
    }

    /** CA 2, 3 y 10 — verificación contra la fuente institucional. */
    private function validarContraSis(string $codSis): void
    {
        $this->assertSisAvailable();

        if (! $this->sis->existePersona($codSis)) {
            throw new SisNoValidoException();       // -> 422
        }
    }

    private function assertSisAvailable(): void
    {
        if (! $this->sis->estaDisponible()) {
            throw new SisNoDisponibleException();   // -> 503
        }
    }
}
