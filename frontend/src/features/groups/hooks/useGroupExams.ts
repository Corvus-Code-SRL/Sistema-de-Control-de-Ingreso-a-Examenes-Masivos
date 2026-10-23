import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getGroupExams } from '../services/groupsService'

/** Consulta contextual con cancelación y reintento. */
export function useGroupExams(groupId: number) {
  return useAsyncResource((signal) => getGroupExams(groupId, signal), [groupId])
}
