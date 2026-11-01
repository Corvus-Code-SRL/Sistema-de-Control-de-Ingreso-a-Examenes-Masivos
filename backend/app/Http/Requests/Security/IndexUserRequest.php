<?php

namespace App\Http\Requests\Security;

/**
 * Listado paginado de cuentas, con búsqueda por nombre o código y filtro por rol vigente.
 *
 * `rol` es el id de un rol del catálogo o `sin-rol` para las cuentas que aún no tienen uno.
 */
class IndexUserRequest extends AdministratorRequest
{
    public const DEFAULT_PER_PAGE = 10;

    public const WITHOUT_ROLE = 'sin-rol';

    private const MAX_PER_PAGE = 50;

    // Un OFFSET desbordado haría fallar la consulta: se acota la página que se puede pedir.
    private const MAX_PAGE = 100000;

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:50'],
            'rol' => ['nullable', 'regex:/^([0-9]{1,9}|' . self::WITHOUT_ROLE . ')$/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PAGE],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'búsqueda',
            'rol' => 'rol',
            'page' => 'página',
            'per_page' => 'cuentas por página',
        ];
    }

    public function search(): ?string
    {
        return $this->validated()['q'] ?? null;
    }

    /** @return int|string|null Id del rol, `sin-rol` o null cuando no se filtra. */
    public function roleFilter()
    {
        $role = $this->validated()['rol'] ?? null;

        if ($role === null || $role === self::WITHOUT_ROLE) {
            return $role;
        }

        return (int) $role;
    }

    public function page(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? self::DEFAULT_PER_PAGE);
    }
}
