import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { createGroup } from '../services/groupsService'
import type { CreateGroupPayload, GroupMutationResponse } from '../types/group.types'

export type MutationStatus = 'idle' | 'submitting' | 'success' | 'error'

export interface UseCreateGroupResult {
  status: MutationStatus
  error: ApiError | null
  /** Envía el registro; devuelve el resultado o null si falló (el error ya queda en `error`). */
  submit: (payload: CreateGroupPayload) => Promise<GroupMutationResponse['data'] | null>
  reset: () => void
}

/**
 * HU-18 — registro de un grupo académico.
 *
 * No usa useAsyncResource: esa carga automáticamente al montar, mientras que
 * un registro se dispara una sola vez, al enviar el formulario.
 */
export function useCreateGroup(): UseCreateGroupResult {
  const [status, setStatus] = useState<MutationStatus>('idle')
  const [error, setError] = useState<ApiError | null>(null)

  const submit = useCallback(async (payload: CreateGroupPayload) => {
    setStatus('submitting')
    setError(null)

    try {
      const result = await createGroup(payload)
      setStatus('success')
      return result
    } catch (cause) {
      const apiError = cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message)
      setError(apiError)
      setStatus('error')
      return null
    }
  }, [])

  const reset = useCallback(() => {
    setStatus('idle')
    setError(null)
  }, [])

  return { status, error, submit, reset }
}