import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getSubjectCareerAssignments } from '../services/subjectsService'
import type { SubjectCareerAssignmentsResult } from '../types/subject.types'

interface AssignmentsSnapshot extends SubjectCareerAssignmentsResult {
  periodId: number | null
  careerId: number | null
}

/**
 * Ofertas de la vista Asignaciones: las del período, con filtro opcional por carrera.
 *
 * Sin período (todavía no se resolvió cuál es el vigente) no consulta y queda cargando. Al cambiar
 * el período o la carrera oculta de inmediato las filas del filtro anterior, y una respuesta
 * tardía de un filtro viejo nunca pisa la del actual: cada petición se aborta al cambiar y además
 * el resultado se descarta si su período o carrera ya no son los pedidos. `careers` son las del
 * selector del período; se conservan mientras llega la respuesta del nuevo filtro.
 */
export function useSubjectCareerAssignments(periodId: number | null, careerId: number | null) {
  const resource = useAsyncResource<AssignmentsSnapshot>(
    useCallback(
      async (signal) => {
        if (periodId === null) {
          return { periodId, careerId, assignments: [], careers: [] }
        }

        return {
          periodId,
          careerId,
          ...(await getSubjectCareerAssignments(periodId, careerId, signal)),
        }
      },
      [periodId, careerId]
    ),
    [periodId, careerId]
  )

  const belongsToCurrentFilter =
    resource.data?.periodId === periodId && resource.data?.careerId === careerId

  const status =
    resource.error !== null
      ? 'error'
      : periodId !== null && resource.status === 'success' && belongsToCurrentFilter
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
