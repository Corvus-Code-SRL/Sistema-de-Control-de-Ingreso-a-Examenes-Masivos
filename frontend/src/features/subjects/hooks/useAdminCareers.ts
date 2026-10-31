import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAdminCareers } from '../services/subjectsService'
import type { Career } from '../types/subject.types'

/** Carreras activas disponibles para la asignación administrativa de materias. */
export function useAdminCareers() {
  const resource = useAsyncResource<Career[]>(
    useCallback((signal) => getAdminCareers(signal), []),
    []
  )

  return {
    careers: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}