import { useAsyncResource, type AsyncResource } from '@/hooks/useAsyncResource'
import { collaboratorsService } from '../services/collaboratorsService'
import type { SentInvitation } from '../types/collaborator.types'

/** Invitaciones enviadas por el docente, de la más reciente a la más antigua. */
export function useSentInvitations(): AsyncResource<SentInvitation[]> {
  return useAsyncResource((signal) => collaboratorsService.listSentInvitations(signal), [])
}
