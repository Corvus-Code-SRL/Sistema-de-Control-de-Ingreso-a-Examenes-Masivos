import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAssignableSubjects } from '../services/subjectsService'
import type { SubjectData } from '../types/subject.types'

interface AssignableSubjectsSnapshot {
  careerId: number | null
  periodId: number | null
  subjects: SubjectData[]
}

/**
 * Materias activas que la carrera seleccionada todavía no ofrece en el período.
 *
 * Sin carrera o sin período no consulta al backend y expone una colección vacía. Al cambiar de
 * carrera o de período oculta de inmediato los resultados anteriores, y una respuesta tardía de una
 * combinación vieja nunca reemplaza a la actual.
 */
export function useAssignableSubjects(careerId: number | null, periodId: number | null) {
  const resource = useAsyncResource<AssignableSubjectsSnapshot>(
    useCallback(
      async (signal) => ({
        careerId,
        periodId,
        subjects:
          careerId === null || periodId === null
            ? []
            : await getAssignableSubjects(careerId, periodId, signal),
      }),
      [careerId, periodId]
    ),
    [careerId, periodId]
  )

  const belongsToCurrentSelection =
    resource.data?.careerId === careerId && resource.data?.periodId === periodId

  const status =
    resource.error !== null
      ? 'error'
      : resource.status === 'success' && belongsToCurrentSelection
        ? 'success'
        : 'loading'

  return {
    subjects: belongsToCurrentSelection ? resource.data?.subjects ?? [] : [],
    status,
    error: resource.error,
    reload: resource.reload,
  }
}
