import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { UNASSIGN_PER_PAGE, getUnassignableSubjects } from '../services/unassignSubjectService'
import type { UnassignableSubjectsResponse } from '../types/unassignSubject.types'

interface Snapshot extends UnassignableSubjectsResponse {
  key: string
}

/**
 * Materias de una carrera en un período para la vista Desvincular.
 *
 * Sin carrera o sin período no consulta. Cada resultado lleva la clave del filtro que lo pidió: si
 * ya no es el actual (carrera, período, búsqueda o página cambiaron) se oculta y no pisa al vigente.
 */
export function useUnassignableSubjects(
  careerId: number | null,
  periodId: number | null,
  search: string,
  page: number
) {
  const key = `${careerId}|${periodId}|${search}|${page}`

  const resource = useAsyncResource<Snapshot>(
    useCallback(
      async (signal) => {
        if (careerId === null || periodId === null) {
          return { key, materias: [], total: 0, page, per_page: UNASSIGN_PER_PAGE }
        }

        const result = await getUnassignableSubjects(
          { careerId, periodId, search, page, perPage: UNASSIGN_PER_PAGE },
          signal
        )

        return { key, ...result }
      },
      // eslint-disable-next-line react-hooks/exhaustive-deps
      [careerId, periodId, search, page]
    ),
    [careerId, periodId, search, page]
  )

  const isCurrent = resource.data?.key === key

  const status =
    resource.error !== null ? 'error' : resource.status === 'success' && isCurrent ? 'success' : 'loading'

  return {
    subjects: isCurrent ? resource.data?.materias ?? [] : [],
    total: isCurrent ? resource.data?.total ?? 0 : 0,
    perPage: resource.data?.per_page ?? UNASSIGN_PER_PAGE,
    status,
    error: resource.error,
    reload: resource.reload,
  } as const
}
