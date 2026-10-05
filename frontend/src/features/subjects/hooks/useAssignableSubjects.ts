import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAssignableSubjects } from '../services/subjectsService'
import type { SubjectData } from '../types/subject.types'

interface AssignableSubjectsSnapshot {
  careerId: number | null
  subjects: SubjectData[]
}

/**
 * Materias activas que todavía pueden asignarse a la carrera seleccionada.
 *
 * Sin carrera seleccionada no consulta al backend y expone una colección vacía.
 * Al cambiar de carrera oculta inmediatamente los resultados de la carrera anterior.
 */
export function useAssignableSubjects(careerId: number | null) {
  const resource = useAsyncResource<AssignableSubjectsSnapshot>(
    useCallback(
      async (signal) => ({
        careerId,
        subjects:
          careerId === null
            ? []
            : await getAssignableSubjects(careerId, signal),
      }),
      [careerId]
    ),
    [careerId]
  )

  const belongsToCurrentCareer =
    resource.data?.careerId === careerId

  const status =
    resource.error !== null
      ? 'error'
      : resource.status === 'success' && belongsToCurrentCareer
        ? 'success'
        : 'loading'

  return {
    subjects: belongsToCurrentCareer
      ? resource.data?.subjects ?? []
      : [],
    status,
    error: resource.error,
    reload: resource.reload,
  }
}