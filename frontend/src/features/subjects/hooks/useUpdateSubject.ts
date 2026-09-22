import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { actualizarMateria } from '../services/subjectsService'
import type {
  SubjectData,
  UpdateSubjectPayload,
} from '../types/subject.types'

export type UpdateSubjectStatus =
  | 'idle'
  | 'submitting'
  | 'success'
  | 'error'

export interface UseUpdateSubjectResult {
  status: UpdateSubjectStatus
  error: ApiError | null
  submit: (
    idMateria: number,
    payload: UpdateSubjectPayload
  ) => Promise<SubjectData | null>
  reset: () => void
}

/** Actualización de una materia existente del catálogo institucional. */
export function useUpdateSubject(): UseUpdateSubjectResult {
  const [status, setStatus] = useState<UpdateSubjectStatus>('idle')
  const [error, setError] = useState<ApiError | null>(null)

  const submit = useCallback(
    async (
      idMateria: number,
      payload: UpdateSubjectPayload
    ): Promise<SubjectData | null> => {
      setStatus('submitting')
      setError(null)

      try {
        const response = await actualizarMateria(idMateria, payload)
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