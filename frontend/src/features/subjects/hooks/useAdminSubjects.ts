import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAdminSubjects } from '../services/subjectsService'
import type { AdminSubjectSummary } from '../types/subject.types'

/**
 * Catálogo institucional visto desde Administración (solo lectura).
 *
 * Cada materia aparece una sola vez, aunque esté asociada a varias carreras.
 * `search` filtra por código o nombre en el servidor; quien llama decide cuándo
 * lanzarla (por ejemplo, tras un debounce).
 */
export function useAdminSubjects(search = '') {
  const resource = useAsyncResource<AdminSubjectSummary[]>(
    useCallback((signal) => getAdminSubjects(search, signal), [search]),
    [search]
  )

  return {
    subjects: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
