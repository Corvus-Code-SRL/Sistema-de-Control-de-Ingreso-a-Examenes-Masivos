<?php

namespace App\Services\Security;

use App\Exceptions\Security\DuplicateAccountException;
use App\Exceptions\Security\GuestCodeTakenException;
use App\Exceptions\Security\GuestCodesExhaustedException;
use App\Exceptions\Security\RoleNotAllowedForAccountException;
use App\Exceptions\Security\SisNoDisponibleException;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\Contracts\SisGateway;
use App\Support\SisCode;
use Generator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    private const GUEST_MIN_NUMBER = 1000;

    private const GUEST_MAX_NUMBER = 9999;

    /** Intentos al azar antes de buscar uno de los códigos que aún quedan libres. */
    private const GUEST_RANDOM_ATTEMPTS = 10;

    private const UNIQUE_VIOLATION = '23505';

    private SisGateway $sis;
    private AuditLogService $auditoria;
    private UserRoleService $roles;

    public function __construct(SisGateway $sis, AuditLogService $auditoria, UserRoleService $roles)
    {
        $this->sis = $sis;
        $this->auditoria = $auditoria;
        $this->roles = $roles;
    }

    /**
     * Registra una cuenta de usuario en SCIEM (HU-001).
     *
     * El código SIS ya no tiene que existir en el SIS: basta con un formato válido para el rol
     * elegido (lo valida StoreUserRequest). Con `sin_codigo_sis` la cuenta es de un docente
     * invitado: el sistema genera su código de acceso INV y la cuenta recibe el rol Invitado
     * salvo que se pida dejarla sin rol. Cuenta, rol y bitácora se guardan en una sola transacción.
     *
     * Un invitado puede traer el código INV que el administrador vio antes de registrar (`cod_invitado`):
     * si otra cuenta lo tomó mientras tanto, el registro responde 409 con un código libre. Sin él, el
     * sistema genera uno.
     *
     * La contraseña temporal solo se guarda hasheada y la cuenta queda obligada a cambiarla en su
     * primer ingreso.
     */
    public function registrar(array $datos, string $actorId): User
    {
        $isGuest = (bool) ($datos['sin_codigo_sis'] ?? false);
        $role = $this->resolveRole($datos, $isGuest);

        $this->assertRoleFitsRegistration($isGuest, $datos['cod_sis'] ?? null, $role);

        return DB::transaction(function () use ($datos, $actorId, $isGuest, $role) {
            $attributes = [
                'nombre'           => $datos['nombre'],
                'apellido_paterno' => $datos['apellido_paterno'],
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'correo'           => $datos['correo'],
                'contrasenia'      => Hash::make($datos['contrasenia_temporal']),
                'debe_cambiar_contrasenia' => true,
                'estado'           => User::ESTADO_ACTIVO,
            ];

            $usuario = $isGuest
                ? $this->createGuestAccount($attributes, $datos['cod_invitado'] ?? null)
                : User::create($attributes + ['cod_sis' => SisCode::normalize($datos['cod_sis'])]);

            $this->auditoria->registrar(
                'CREAR',
                'usuario',
                null,
                $usuario->toArray(),   // AuditLogService filtra 'contrasenia'
                $actorId
            );

            if ($role !== null) {
                $this->roles->assignInitialRole($usuario, $role, $actorId);
            }

            return $usuario;
        });
    }

    /**
     * Paso previo al registro: avisa si el código ya tiene cuenta y, si el SIS reconoce a la
     * persona, devuelve sus datos para precargar el formulario. El duplicado se revisa antes que el
     * SIS: así se detecta aunque el SIS no responda. Que el SIS no la conozca no impide
     * registrarla: devuelve null y quien registra escribe los datos.
     *
     * @return array<string, mixed>|null
     */
    public function verifySisCode(string $codSis): ?array
    {
        $codSis = SisCode::normalize($codSis);

        $existing = User::whereRaw('upper(cod_sis) = ?', [$codSis])->first();

        if ($existing !== null) {
            throw new DuplicateAccountException($existing->nombre_completo, $existing->id_usuario);   // -> 422
        }

        $this->assertSisAvailable();

        return $this->sis->existePersona($codSis) ? $this->sis->personData($codSis) : null;
    }

    /**
     * Rol que recibe la cuenta. Un docente invitado que no indica rol queda como Invitado; un
     * `id_rol` explícito en null la deja sin rol por ahora.
     */
    private function resolveRole(array $datos, bool $isGuest): ?Role
    {
        if (isset($datos['id_rol'])) {
            return Role::findOrFail((int) $datos['id_rol']);
        }

        if ($isGuest && ! array_key_exists('id_rol', $datos)) {
            return Role::where('nombre_rol', Role::INVITADO)->firstOrFail();
        }

        return null;
    }

    private function assertRoleFitsRegistration(bool $isGuest, ?string $code, ?Role $role): void
    {
        if ($isGuest) {
            if ($role !== null && $role->nombre_rol !== Role::INVITADO) {
                throw RoleNotAllowedForAccountException::guestAccount();
            }

            return;
        }

        $this->roles->assertRoleFitsAccount((string) $code, $role === null ? null : $role->nombre_rol);
    }

    /** Código INV libre en este momento, para mostrarlo al administrador antes de registrar la cuenta. */
    public function suggestGuestCode(): string
    {
        foreach ($this->guestCodeCandidates() as $code) {
            if (! $this->guestCodeExists($code)) {
                return $code;
            }
        }

        throw new GuestCodesExhaustedException();
    }

    private function createGuestAccount(array $attributes, ?string $requestedCode): User
    {
        if ($requestedCode !== null) {
            return $this->createGuestAccountWithCode($attributes, $requestedCode);
        }

        foreach ($this->guestCodeCandidates() as $code) {
            try {
                // Una transacción anidada es un savepoint: la colisión no deja abortada la externa.
                return DB::transaction(fn () => User::create($attributes + ['cod_sis' => $code]));
            } catch (QueryException $e) {
                if (! $this->isCodeCollision($e)) {
                    throw $e;
                }
            }
        }

        throw new GuestCodesExhaustedException();
    }

    private function createGuestAccountWithCode(array $attributes, string $code): User
    {
        if ($this->guestCodeExists($code)) {
            throw new GuestCodeTakenException($code, $this->suggestGuestCode());
        }

        try {
            return DB::transaction(fn () => User::create($attributes + ['cod_sis' => $code]));
        } catch (QueryException $e) {
            // Otra petición tomó el código entre la comprobación y el INSERT.
            if (! $this->isCodeCollision($e)) {
                throw $e;
            }

            throw new GuestCodeTakenException($code, $this->suggestGuestCode());
        }
    }

    private function guestCodeExists(string $code): bool
    {
        return User::whereRaw('upper(cod_sis) = ?', [$code])->exists();
    }

    /**
     * Códigos INV1000 a INV9999: primero al azar y, si todos chocan, los que realmente quedan
     * libres. Cuando no queda ninguno, el generador termina y el registro informa el error.
     *
     * @return Generator<int, string>
     */
    private function guestCodeCandidates(): Generator
    {
        for ($attempt = 0; $attempt < self::GUEST_RANDOM_ATTEMPTS; $attempt++) {
            yield SisCode::GUEST_PREFIX . random_int(self::GUEST_MIN_NUMBER, self::GUEST_MAX_NUMBER);
        }

        $used = User::query()
            ->where('cod_sis', 'like', SisCode::GUEST_PREFIX . '%')
            ->pluck('cod_sis')
            ->all();

        $free = array_diff($this->allGuestCodes(), $used);
        shuffle($free);

        foreach (array_slice($free, 0, self::GUEST_RANDOM_ATTEMPTS) as $code) {
            yield $code;
        }
    }

    /** @return array<int, string> */
    private function allGuestCodes(): array
    {
        return array_map(
            fn (int $number) => SisCode::GUEST_PREFIX . $number,
            range(self::GUEST_MIN_NUMBER, self::GUEST_MAX_NUMBER)
        );
    }

    private function isCodeCollision(QueryException $e): bool
    {
        return (string) $e->getCode() === self::UNIQUE_VIOLATION && strpos($e->getMessage(), 'cod_sis') !== false;
    }

    private function assertSisAvailable(): void
    {
        if (! $this->sis->estaDisponible()) {
            throw new SisNoDisponibleException();   // -> 503
        }
    }
}
