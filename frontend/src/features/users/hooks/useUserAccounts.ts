import { useCallback } from 'react'
import { useAsyncResource, type AsyncResource } from '@/hooks/useAsyncResource'
import { getUserAccounts } from '../services/usersService'
import type { UserAccounts } from '../types/users.types'

/** Listado de cuentas con el usuario que ejecuta la operación. */
export function useUserAccounts(): AsyncResource<UserAccounts> {
  return useAsyncResource<UserAccounts>(
    useCallback((signal) => getUserAccounts(signal), []),
    []
  )
}
