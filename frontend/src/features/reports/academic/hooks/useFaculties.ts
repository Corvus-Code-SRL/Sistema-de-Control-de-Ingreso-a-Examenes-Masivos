import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getFaculties } from '../services/facultiesService'
import type { Faculty } from '../types/alienAttempts.types'

/** Catálogo de facultades del filtro; si falla, el filtro se deshabilita. */
export function useFaculties() {
  const resource = useAsyncResource<Faculty[]>(
    useCallback((signal) => getFaculties(signal), []),
    []
  )

  return {
    faculties: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
