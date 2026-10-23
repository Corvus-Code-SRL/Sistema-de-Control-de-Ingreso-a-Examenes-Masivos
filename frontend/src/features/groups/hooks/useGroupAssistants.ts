import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getGroupAssistants } from '../services/groupsService'

/** Consulta contextual con cancelación y reintento. */
export function useGroupAssistants(groupId: number) {
  return useAsyncResource((signal) => getGroupAssistants(groupId, signal), [groupId])
}
