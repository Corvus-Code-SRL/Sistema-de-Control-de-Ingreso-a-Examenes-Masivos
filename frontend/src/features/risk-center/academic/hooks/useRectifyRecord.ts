import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { rectifyRecord } from '../services/riskRectificationService'
import type { RectificationInput, RectifiedRecord } from '../types/riskRectification.types'

/**
 * Envía la rectificación. `submit` devuelve el registro ya rectificado, o null si el servidor lo rechazó
 * (el motivo queda en `error`); un error nunca se confunde con éxito.
 */
export function useRectifyRecord() {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)

  const submit = useCallback(
    async (recordId: number, input: RectificationInput): Promise<RectifiedRecord | null> => {
      setIsSubmitting(true)
      setError(null)

      try {
        return await rectifyRecord(recordId, input)
      } catch (cause) {
        setError(cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message))

        return null
      } finally {
        setIsSubmitting(false)
      }
    },
    []
  )

  const clearError = useCallback(() => setError(null), [])

  return { isSubmitting, error, submit, clearError }
}
