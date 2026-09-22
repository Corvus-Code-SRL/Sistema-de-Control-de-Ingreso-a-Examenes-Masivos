import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { examsService } from '../services/examsService'
import type { Exam } from '../types/exams.types'

/** Exámenes del docente actual (vista Programados). */
export function useExams() {
  const resource = useAsyncResource<Exam[]>(
    useCallback((signal) => examsService.listExams(signal), []),
    []
  )

  return {
    exams: resource.data ?? [],
    isLoading: resource.status === 'loading',
    isEmpty: resource.status === 'success' && (resource.data?.length ?? 0) === 0,
    error: resource.error,
    reload: resource.reload,
  }
}
