import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getGroupAssistants } from '../services/groupsService'
import type { GroupAssistant } from '../types/group.types'

/** Auxiliares del grupo, de solo lectura: añadir y quitar viven en «Mis auxiliares». */
export function useGroupAssistants(groupId: number) {
  const resource = useAsyncResource<GroupAssistant[]>(
    useCallback((signal) => getGroupAssistants(groupId, signal), [groupId]),
    [groupId]
  )

  return {
    assistants: resource.data ?? [],
    isLoading: resource.status === 'loading',
    isEmpty: resource.status === 'success' && (resource.data?.length ?? 0) === 0,
    error: resource.error,
    reload: resource.reload,
  }
}
