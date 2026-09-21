import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import type { SubjectCareer } from '@/features/subjects'
import type { ApiError } from '@/lib/api-client'
import { getGroupsByPair } from '../services/groupsService'
import type { Group, SubjectGroups } from '../types/group.types'

export interface UseSubjectGroupsResult {
  subject: SubjectCareer | null
  /** Todos los grupos del par, también los de otros docentes. */
  groups: Group[]
  isLoading: boolean
  /** El par existe y está activo, pero todavía no tiene grupos. */
  isEmpty: boolean
  /** Hay grupos en el par, pero ninguno es del docente. */
  hasNoOwnGroups: boolean
  error: ApiError | null
  reload: () => void
}

/**
 * Grupos de un par materia-carrera.
 *
 * El listado no se filtra por docente: mostrar solo los grupos propios ocultaría
 * que la materia ya está cubierta por un colega.
 */
export function useSubjectGroups(careerId: number, subjectId: number): UseSubjectGroupsResult {
  const resource = useAsyncResource<SubjectGroups>(
    useCallback(
      (signal) => getGroupsByPair(careerId, subjectId, signal),
      [careerId, subjectId]
    ),
    [careerId, subjectId]
  )

  const loaded = resource.status === 'success' && resource.data !== null

  return {
    subject: resource.data?.subject ?? null,
    groups: resource.data?.groups ?? [],
    isLoading: resource.status === 'loading',
    isEmpty: loaded && resource.data!.meta.total === 0,
    hasNoOwnGroups: loaded && resource.data!.meta.total_mios === 0,
    error: resource.error,
    reload: resource.reload,
  }
}
