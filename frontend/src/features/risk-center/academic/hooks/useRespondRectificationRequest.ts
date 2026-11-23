import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { acceptRectificationRequest, rejectRectificationRequest } from '../services/rectificationRequestsService'
import type { RectificationRequestCard } from '../types/riskRectification.types'

/**
 * Responde una solicitud: aceptar o rechazar. Cada método devuelve la tarjeta actualizada, o null si el
 * servidor la rechazó (el motivo queda en `error`); un error nunca se confunde con éxito.
 */
export function useRespondRectificationRequest() {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)

  const run = useCallback(async (action: () => Promise<RectificationRequestCard>) => {
    setIsSubmitting(true)
    setError(null)

    try {
      return await action()
    } catch (cause) {
      setError(cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message))

      return null
    } finally {
      setIsSubmitting(false)
    }
  }, [])

  const accept = useCallback((id: number) => run(() => acceptRectificationRequest(id)), [run])

  const reject = useCallback(
    (id: number, input: { motivo: string; evidencia: File | null }) => run(() => rejectRectificationRequest(id, input)),
    [run]
  )

  const clearError = useCallback(() => setError(null), [])

  return { isSubmitting, error, accept, reject, clearError }
}
