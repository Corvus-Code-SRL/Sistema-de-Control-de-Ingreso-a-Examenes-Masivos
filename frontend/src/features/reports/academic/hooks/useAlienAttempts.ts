import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAlienAttempts } from '../services/alienAttemptsService'
import type { AlienAttemptsFilters, AlienAttemptsPage } from '../types/alienAttempts.types'

/**
 * Página del reporte. Mientras llega la nueva página, `page` conserva la anterior y
 * `isStale` lo indica para que la vista atenúe la tabla sin desmontarla.
 */
export function useAlienAttempts(filters: AlienAttemptsFilters) {
  const resource = useAsyncResource<AlienAttemptsPage>(
    useCallback((signal) => getAlienAttempts(filters, signal), [
      filters.desde,
      filters.hasta,
      filters.facultad,
      filters.page,
    ]),
    [filters.desde, filters.hasta, filters.facultad, filters.page]
  )

  return {
    page: resource.data,
    isStale: resource.status === 'loading' && resource.data !== null,
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}