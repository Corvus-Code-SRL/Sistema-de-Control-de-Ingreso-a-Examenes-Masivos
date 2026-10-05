import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getGroupExams } from '../services/groupsService'
import type { GroupExam } from '../types/group.types'

/** Exámenes que incluyen el grupo: «vacío» (sin exámenes) y «error» son estados distintos. */
export function useGroupExams(groupId: number) {
  const resource = useAsyncResource<GroupExam[]>(
    useCallback((signal) => getGroupExams(groupId, signal), [groupId]),
    [groupId]
  )

  return {
    exams: resource.data ?? [],
    isLoading: resource.status === 'loading',
    isEmpty: resource.status === 'success' && (resource.data?.length ?? 0) === 0,
    error: resource.error,
    reload: resource.reload,
  }
}
