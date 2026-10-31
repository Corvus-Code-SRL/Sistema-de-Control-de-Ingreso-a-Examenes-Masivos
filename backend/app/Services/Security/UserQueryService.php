<?php

namespace App\Services\Security;

use App\Models\User;
use App\Support\Database\AccentInsensitiveSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta de cuentas para su administración.
 *
 * El rol vigente se carga junto con las cuentas para no lanzar una consulta
 * por fila al serializarlas.
 */
class UserQueryService
{
    private const FULL_NAME = "CONCAT_WS(' ', usuario.nombre, usuario.apellido_paterno, usuario.apellido_materno)";

    /**
     * Cuentas de una página. `$search` coincide con el nombre o el código, sin distinguir
     * mayúsculas ni tildes, y cada palabra debe coincidir. `$roleFilter` es un id de rol o `sin-rol`.
     *
     * @param  int|string|null  $roleFilter
     */
    public function paginate(?string $search, $roleFilter, int $page, int $perPage): LengthAwarePaginator
    {
        $query = User::query()
            ->with('activeRoles')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombre')
            ->orderBy('id_usuario');

        foreach ($this->searchTerms($search) as $term) {
            $this->matchTerm($query, AccentInsensitiveSearch::pattern($term));
        }

        if ($roleFilter === 'sin-rol') {
            $query->whereDoesntHave('activeRoles');
        } elseif ($roleFilter !== null) {
            $query->whereHas('activeRoles', fn (Builder $role) => $role->where('rol.id_rol', $roleFilter));
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /** El historial se ordena del rol más reciente al más antiguo. */
    public function findUser(User $user): User
    {
        return $user->load([
            'activeRoles',
            'roles' => fn ($query) => $query->orderByPivot('fecha_inicio', 'desc'),
        ]);
    }

    public function meta(?string $currentUserId): array
    {
        return ['id_usuario_actual' => $currentUserId];
    }

    private function matchTerm(Builder $query, string $pattern): void
    {
        $query->where(function (Builder $account) use ($pattern) {
            $account->whereRaw(AccentInsensitiveSearch::condition(self::FULL_NAME), [$pattern])
                ->orWhereRaw(AccentInsensitiveSearch::condition('usuario.cod_sis'), [$pattern]);
        });
    }

    /** @return array<int, string> */
    private function searchTerms(?string $search): array
    {
        $normalized = AccentInsensitiveSearch::normalize((string) $search);

        return $normalized === '' ? [] : preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    }
}
