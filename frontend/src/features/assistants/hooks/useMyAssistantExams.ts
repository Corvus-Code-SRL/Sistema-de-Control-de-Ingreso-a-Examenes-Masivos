import { useAsyncResource, type AsyncResource } from '@/hooks/useAsyncResource'
import { assistantsService } from '../services/assistantsService'
import type { AssistantExam } from '../types/assistant.types'

/** Exámenes vigentes del auxiliar con el ambiente que le asignó el docente. */
export function useMyAssistantExams(): AsyncResource<AssistantExam[]> {
  return useAsyncResource<AssistantExam[]>((signal) => assistantsService.listMyExams(signal), [])
}
