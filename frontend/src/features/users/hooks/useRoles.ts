import { useCallback } from 'react'
import { useAsyncResource, type AsyncResource } from '@/hooks/useAsyncResource'
import { getRoles } from '../services/usersService'
import type { Role } from '../types/users.types'

/** Catálogo de roles asignables: solo los definidos y activos en SCIEM. */
export function useRoles(): AsyncResource<Role[]> {
  return useAsyncResource<Role[]>(
    useCallback((signal) => getRoles(signal), []),
    []
  )
}
