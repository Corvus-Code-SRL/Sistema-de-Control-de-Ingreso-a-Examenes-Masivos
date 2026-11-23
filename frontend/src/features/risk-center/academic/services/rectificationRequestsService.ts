import { apiClient } from '@/lib/api-client'
import type { RectificationInbox, RectificationRequestCard } from '../types/riskRectification.types'

const BASE = '/solicitudes-rectificacion'

/** Bandeja del docente: pendientes vigentes primero y, después, las respondidas y vencidas. */
export async function listRectificationRequests(signal?: AbortSignal): Promise<RectificationInbox> {
  const response = await apiClient<{
    data?: RectificationRequestCard[]
    meta?: { total_pendientes?: number }
  }>(BASE, { signal })

  return { solicitudes: response.data ?? [], totalPendientes: response.meta?.total_pendientes ?? 0 }
}

/** Pendientes no vencidas, para el contador del menú. */
export async function countPendingRectificationRequests(signal?: AbortSignal): Promise<number> {
  const response = await apiClient<{ data?: { total_pendientes?: number } }>(`${BASE}/conteo`, { signal })

  return response.data?.total_pendientes ?? 0
}

/** Acepta: el registro sale de la Central. */
export async function acceptRectificationRequest(requestId: number): Promise<RectificationRequestCard> {
  const response = await apiClient<{ data: RectificationRequestCard }>(`${BASE}/${requestId}/aceptar`, {
    method: 'POST',
  })

  return response.data
}

/** Rechaza con motivo y, si se quiere, una imagen; viaja como multipart. */
export async function rejectRectificationRequest(
  requestId: number,
  input: { motivo: string; evidencia: File | null }
): Promise<RectificationRequestCard> {
  const body = new FormData()

  body.append('motivo', input.motivo)
  if (input.evidencia !== null) body.append('evidencia', input.evidencia)

  const response = await apiClient<{ data: RectificationRequestCard }>(`${BASE}/${requestId}/rechazar`, {
    method: 'POST',
    body,
  })

  return response.data
}

export function getRequestReportEvidence(requestId: number, signal?: AbortSignal): Promise<Blob> {
  return apiClient<Blob>(`${BASE}/${requestId}/evidencia-reporte`, { responseType: 'blob', signal })
}

export function getRequestRejectionEvidence(requestId: number, signal?: AbortSignal): Promise<Blob> {
  return apiClient<Blob>(`${BASE}/${requestId}/evidencia-rechazo`, { responseType: 'blob', signal })
}
