import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAdminSubjects } from '../services/subjectsService'
import type { AdminSubjectSummary } from '../types/subject.types'

/**
 * Catálogo institucional visto desde Administración.
 *
 * Cada materia aparece una sola vez, aunque esté asociada a varias carreras.
 */
export function useAdminSubjects() {
  const resource = useAsyncResource<AdminSubjectSummary[]>(
    useCallback((signal) => getAdminSubjects(signal), []),
    []
  )

  return {
    subjects: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}