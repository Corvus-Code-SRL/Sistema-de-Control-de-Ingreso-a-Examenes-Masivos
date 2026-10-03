import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAssignableSubjects } from '../services/subjectsService'
import type { SubjectData } from '../types/subject.types'

/**
 * Materias activas que todavía pueden asignarse a la carrera seleccionada.
 *
 * Sin carrera seleccionada no consulta al backend y expone una colección vacía.
 */
export function useAssignableSubjects(careerId: number | null) {
  const resource = useAsyncResource<SubjectData[]>(
    useCallback(
      (signal) => {
        if (careerId === null) {
          return Promise.resolve([])
        }

        return getAssignableSubjects(careerId, signal)
      },
      [careerId]
    ),
    [careerId]
  )

  return {
    subjects: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}