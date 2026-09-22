import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { updateGroup } from '../services/groupsService'
import type { GroupMutationResponse, UpdateGroupPayload } from '../types/group.types'
import type { MutationStatus } from './useCreateGroup'

export interface UseUpdateGroupResult {
  status: MutationStatus
  error: ApiError | null
  submit: (
    groupId: number,
    payload: UpdateGroupPayload
  ) => Promise<GroupMutationResponse['data'] | null>
  reset: () => void
}

/** Actualización de un grupo académico existente. */
export function useUpdateGroup(): UseUpdateGroupResult {
  const [status, setStatus] = useState<MutationStatus>('idle')
  const [error, setError] = useState<ApiError | null>(null)

  const submit = useCallback(async (groupId: number, payload: UpdateGroupPayload) => {
    setStatus('submitting')
    setError(null)

    try {
      const result = await updateGroup(groupId, payload)
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