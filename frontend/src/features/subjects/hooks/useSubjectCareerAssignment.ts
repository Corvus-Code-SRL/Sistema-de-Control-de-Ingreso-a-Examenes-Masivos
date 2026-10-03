import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { assignSubjectToCareer } from '../services/subjectsService'
import type {
  SubjectCareerAssignment,
  SubjectCareerAssignmentPayload,
} from '../types/subject.types'

export type SubjectCareerAssignmentStatus =
  | 'idle'
  | 'submitting'
  | 'success'
  | 'error'

export interface UseSubjectCareerAssignmentResult {
  status: SubjectCareerAssignmentStatus
  error: ApiError | null
  submit: (
    careerId: number,
    payload: SubjectCareerAssignmentPayload
  ) => Promise<SubjectCareerAssignment | null>
  reset: () => void
}

/** Asigna una materia existente del catálogo institucional a una carrera. */
export function useSubjectCareerAssignment(): UseSubjectCareerAssignmentResult {
  const [status, setStatus] =
    useState<SubjectCareerAssignmentStatus>('idle')
  const [error, setError] = useState<ApiError | null>(null)

  const submit = useCallback(
    async (
      careerId: number,
      payload: SubjectCareerAssignmentPayload
    ): Promise<SubjectCareerAssignment | null> => {
      setStatus('submitting')
      setError(null)

      try {
        const response = await assignSubjectToCareer(careerId, payload)

        setStatus('success')

        return response.data
      } catch (cause) {
        const apiError =
          cause instanceof ApiError
            ? cause
            : new ApiError(0, (cause as Error).message)

        setError(apiError)
        setStatus('error')

        return null
      }
    },
    []
  )

  const reset = useCallback(() => {
    setStatus('idle')
    setError(null)
  }, [])

  return {
    status,
    error,
    submit,
    reset,
  }
}