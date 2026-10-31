import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getUserAccounts } from '../services/usersService'
import type { AccountFilters, UserAccounts } from '../types/users.types'

interface Result {
  key: string
  accounts: UserAccounts
}

/**
 * Página de cuentas para unos filtros, con el usuario que ejecuta la operación.
 *
 * Una respuesta que llega tarde nunca pisa a una más nueva: `useAsyncResource` cancela la petición
 * anterior al cambiar los filtros. Mientras llega la nueva se conserva la página anterior en
 * pantalla; `isStale` avisa que esos datos ya no son los de los filtros actuales.
 */
export function useUserAccounts({ q, rol, page }: AccountFilters) {
  const key = `${q}|${rol ?? ''}|${page}`

  const resource = useAsyncResource<Result>(
    useCallback(
      async (signal) => ({ key, accounts: await getUserAccounts({ q, rol, page }, signal) }),
      // La clave resume los tres filtros.
      // eslint-disable-next-line react-hooks/exhaustive-deps
      [key]
    ),
    [key]
  )

  return {
    accounts: resource.data?.accounts ?? null,
    isStale: resource.data !== null && resource.data.key !== key,
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
