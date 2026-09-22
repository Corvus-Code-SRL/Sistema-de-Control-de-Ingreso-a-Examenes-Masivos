import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import type { ApiError } from '@/lib/api-client'
import { getUserAccount } from '../services/usersService'
import type { UserAccountWithContext } from '../types/users.types'

export interface UseUserAccountResult {
  detail: UserAccountWithContext | null
  isLoading: boolean
  isNotFound: boolean
  /** Cualquier fallo que no sea «no existe»; la vista ofrece reintentar. */
  error: ApiError | null
  reload: () => void
}

/** Detalle de una cuenta con su historial de roles. */
export function useUserAccount(userId: string): UseUserAccountResult {
  const resource = useAsyncResource<UserAccountWithContext>(
    useCallback((signal) => getUserAccount(userId, signal), [userId]),
    [userId]
  )

  const isNotFound = resource.error?.isNotFound ?? false

  return {
    detail: resource.data,
    isLoading: resource.status === 'loading',
    isNotFound,
    error: isNotFound ? null : resource.error,
    reload: resource.reload,
  }
}
