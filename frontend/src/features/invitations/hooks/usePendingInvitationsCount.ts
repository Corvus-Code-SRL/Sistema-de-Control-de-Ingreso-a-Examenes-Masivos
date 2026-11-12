import { useEffect } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { listInvitations } from '../services/invitationsService'
import type { InvitationListResponse } from '../types/invitation.types'

/** Evento que la bandeja emite al aceptar o rechazar, para que el contador del menú se actualice. */
export const INVITATIONS_CHANGED_EVENT = 'sciem:invitaciones-cambiaron'

export function notifyInvitationsChanged(): void {
  window.dispatchEvent(new Event(INVITATIONS_CHANGED_EVENT))
}

/**
 * Invitaciones por responder, para el contador de «Invitaciones recibidas» del menú (CA-1). Sin
 * `enabled` no pide nada: el menú solo lo activa si la entrada está visible.
 */
export function usePendingInvitationsCount(enabled: boolean): number {
  const resource = useAsyncResource(
    (signal) =>
      enabled ? listInvitations('pendientes', signal) : Promise.resolve<InvitationListResponse>({ data: [] }),
    [enabled]
  )
  const { reload } = resource

  useEffect(() => {
    if (!enabled) return undefined

    window.addEventListener(INVITATIONS_CHANGED_EVENT, reload)

    return () => window.removeEventListener(INVITATIONS_CHANGED_EVENT, reload)
  }, [enabled, reload])

  return resource.data?.meta?.total_pendientes ?? 0
}