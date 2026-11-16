import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { collaboratorsService } from '../services/collaboratorsService'
import type { SendInvitationPayload, SentInvitation } from '../types/collaborator.types'

export interface SendInvitation {
  sending: boolean
  /** El rechazo del último envío, con el mensaje del servidor (409, 403, 422…); null si salió bien. */
  error: ApiError | null
  /** Envía la invitación; devuelve la creada, o null si el servidor la rechazó. */
  send: (examId: number, payload: SendInvitationPayload) => Promise<SentInvitation | null>
  clearError: () => void
}

/** Envío de una invitación. Mientras hay uno en curso no se acepta otro, para no duplicar la petición. */
export function useSendInvitation(): SendInvitation {
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)

  const send = useCallback(async (examId: number, payload: SendInvitationPayload) => {
    setSending(true)
    setError(null)

    try {
      return await collaboratorsService.sendInvitation(examId, payload)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message))

      return null
    } finally {
      setSending(false)
    }
  }, [])

  const clearError = useCallback(() => setError(null), [])

  return { sending, error, send, clearError }
}
