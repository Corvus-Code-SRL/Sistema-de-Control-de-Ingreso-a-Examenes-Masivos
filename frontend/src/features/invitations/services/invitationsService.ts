import { apiClient, apiPost } from '@/lib/api-client'
import type {
  InvitationDetail,
  InvitationDetailResponse,
  InvitationListResponse,
  InvitationTab,
} from '../types/invitation.types'

/**
 * Bandeja de invitaciones recibidas del usuario autenticado (HU-027). El servidor devuelve solo las
 * dirigidas a él, de la más reciente a la más antigua.
 */
export function listInvitations(tab: InvitationTab, signal?: AbortSignal): Promise<InvitationListResponse> {
  return apiClient<InvitationListResponse>('/invitaciones', { query: { estado: tab }, signal })
}

export async function getInvitation(id: number, signal?: AbortSignal): Promise<InvitationDetail> {
  const response = await apiClient<InvitationDetailResponse>(`/invitaciones/${id}`, { signal })

  return response.data
}

/** Acepta sumando los grupos elegidos. 409 si el examen ya no está «Programado». */
export async function acceptInvitation(id: number, groupIds: number[]): Promise<InvitationDetail> {
  const response = await apiPost<InvitationDetailResponse>(`/invitaciones/${id}/aceptar`, { grupos: groupIds })

  return response.data
}

/** Rechaza; el motivo es opcional. 409 si ya no se puede responder. */
export async function rejectInvitation(id: number, reason: string): Promise<InvitationDetail> {
  const motivo = reason.trim()
  const response = await apiPost<InvitationDetailResponse>(`/invitaciones/${id}/rechazar`, {
    motivo: motivo === '' ? null : motivo,
  })

  return response.data
}
