import { useEffect } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { countPendingRectificationRequests } from '../services/rectificationRequestsService'

/** Evento que la bandeja emite al aceptar o rechazar, para que el contador del menú se actualice. */
export const RECTIFICATION_REQUESTS_CHANGED_EVENT = 'sciem:solicitudes-rectificacion-cambiaron'

export function notifyRectificationRequestsChanged(): void {
  window.dispatchEvent(new Event(RECTIFICATION_REQUESTS_CHANGED_EVENT))
}

/**
 * Solicitudes de rectificación por responder (no vencidas), para el contador del menú. Sin `enabled` no pide
 * nada: el menú solo lo activa si la entrada está visible. Si la respuesta no trae el dato, cuenta cero.
 */
export function usePendingRectificationRequestsCount(enabled: boolean): number {
  const resource = useAsyncResource(
    (signal) => (enabled ? countPendingRectificationRequests(signal) : Promise.resolve(0)),
    [enabled]
  )
  const { reload } = resource

  useEffect(() => {
    if (!enabled) return undefined

    window.addEventListener(RECTIFICATION_REQUESTS_CHANGED_EVENT, reload)

    return () => window.removeEventListener(RECTIFICATION_REQUESTS_CHANGED_EVENT, reload)
  }, [enabled, reload])

  return resource.data ?? 0
}
