<?php

namespace App\Services\Security;

use App\Exceptions\Security\DuplicateAccountException;
use App\Exceptions\Security\SisNoDisponibleException;
use App\Exceptions\Security\SisNoValidoException;
use App\Models\User;
use App\Services\Security\Contracts\SisGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
     * La cuenta se crea SIN rol asignado (CA 5): la asignación de rol
     * es responsabilidad de la HU-004.
     */
    public function registrar(array $datos): User
    {
        $this->validarContraSis($datos['cod_sis']);

        return DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'nombre'           => $datos['nombre'],
                'apellido_paterno' => $datos['apellido_paterno'],
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'correo'           => $datos['correo'],
                'cod_sis'          => $datos['cod_sis'],
                'contrasenia'      => Hash::make($this->generarClaveInicial()),
                'estado'           => User::ESTADO_ACTIVO,
            ]);

            // CA 5: no se inserta nada en usuario_rol. La cuenta nace sin rol.

            // CA 6: trazabilidad
            $this->auditoria->registrar(
                'CREAR',
                'usuario',
                null,
                $usuario->toArray()   // AuditLogService filtra 'contrasenia'
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

    /**
     * PENDIENTE DE DEFINICIÓN CON EL EQUIPO: el mockup no pide contraseña,
     * pero la columna es NOT NULL. Falta decidir si se envía por correo
     * o si el usuario la establece en su primer acceso.
     */
    private function generarClaveInicial(): string
    {
        return Str::random(12);
    }
}
