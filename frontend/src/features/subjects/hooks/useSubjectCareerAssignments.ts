import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getSubjectCareerAssignments } from '../services/subjectsService'
import type { SubjectCareerAssignmentsResult } from '../types/subject.types'

interface AssignmentsSnapshot extends SubjectCareerAssignmentsResult {
  careerId: number | null
}

/**
 * Pares materia-carrera de la pestaña Asignaciones.
 *
 * Sin carrera trae todos; con `careerId` el servidor devuelve solo los de esa carrera. Al cambiar el
 * filtro oculta de inmediato las filas del filtro anterior. `careers` son las carreras con al menos
 * un par, que no dependen del filtro: se conservan mientras llega la respuesta del nuevo filtro.
 * `reload` vuelve a pedir la lista conservando el filtro, que es lo que hace aparecer una
 * asignación recién creada.
 */
export function useSubjectCareerAssignments(careerId: number | null) {
  const resource = useAsyncResource<AssignmentsSnapshot>(
    useCallback(
      async (signal) => ({
        careerId,
        ...(await getSubjectCareerAssignments(careerId, signal)),
      }),
      [careerId]
    ),
    [careerId]
  )

  const belongsToCurrentFilter = resource.data?.careerId === careerId

  const status =
    resource.error !== null
      ? 'error'
      : resource.status === 'success' && belongsToCurrentFilter
        ? 'success'
        : 'loading'

  return {
    assignments: belongsToCurrentFilter ? resource.data?.assignments ?? [] : [],
    careers: resource.data?.careers ?? [],
    status,
    error: resource.error,
    reload: resource.reload,
  } as const
}
