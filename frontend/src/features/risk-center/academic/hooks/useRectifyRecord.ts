import { useCallback, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { requestRectification, rectifyWithoutConfirmation } from '../services/riskRectificationService'
import type {
  RectificationInput,
  RectifiedRecord,
  SentRectificationRequest,
} from '../types/riskRectification.types'

/**
 * Envía un cuerpo de motivo al servidor. `submit` devuelve la respuesta, o null si el servidor la rechazó
 * (el motivo queda en `error`); un error nunca se confunde con éxito.
 */
function useReasonSubmit<TResult>(action: (recordId: number, input: RectificationInput) => Promise<TResult>) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)

  const submit = useCallback(
    async (recordId: number, input: RectificationInput): Promise<TResult | null> => {
      setIsSubmitting(true)
      setError(null)

      try {
        return await action(recordId, input)
      } catch (cause) {
        setError(cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message))

        return null
      } finally {
        setIsSubmitting(false)
      }
    },
    [action]
  )

  const clearError = useCallback(() => setError(null), [])

  return { isSubmitting, error, submit, clearError }
}

/** Envía la solicitud de rectificación al docente que aprobó el reporte (el registro sigue activo). */
export function useRequestRectification() {
  return useReasonSubmit<SentRectificationRequest>(requestRectification)
}

/** Anula el registro sin confirmación del docente, cuando este lleva inactivo el plazo configurado. */
export function useRectifyWithoutConfirmation() {
  return useReasonSubmit<RectifiedRecord>(rectifyWithoutConfirmation)
}
