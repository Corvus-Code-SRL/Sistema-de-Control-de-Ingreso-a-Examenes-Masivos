import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { exceptionalStudentService } from '../services/exceptionalStudentService'
import type { ExceptionalExam } from '../types/exceptional-student.types'

/** Exámenes del docente, para elegir en cuál registrar al estudiante. */
export function useExceptionalExams() {
  return useAsyncResource<ExceptionalExam[]>(
    useCallback(async (signal) => (await exceptionalStudentService.exams(signal)).data, []),
    []
  )
}
