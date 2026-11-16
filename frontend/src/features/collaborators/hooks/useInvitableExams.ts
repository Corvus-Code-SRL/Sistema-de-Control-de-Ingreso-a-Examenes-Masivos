import { useAsyncResource, type AsyncResource } from '@/hooks/useAsyncResource'
import { collaboratorsService } from '../services/collaboratorsService'
import type { InvitableExam } from '../types/collaborator.types'

/** Exámenes PROGRAMADOS propios, del más próximo al más lejano: las opciones del desplegable. */
export function useInvitableExams(): AsyncResource<InvitableExam[]> {
  return useAsyncResource((signal) => collaboratorsService.listInvitableExams(signal), [])
}
