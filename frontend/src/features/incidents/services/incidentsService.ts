import { apiClient, apiPost } from '@/lib/api-client'
import type {
  IncidentFilter,
  IncidentListResponse,
  IncidentDetailResponse,
} from '../types/incident.types'

/** Lista las incidencias del docente autenticado. */
export async function listIncidents(
  filter: IncidentFilter,
  examId?: number,
  orden?: 'asc' | 'desc',
  signal?: AbortSignal
): Promise<IncidentListResponse> {
  return apiClient<IncidentListResponse>('/docente/incidencias', {
    signal,
    query: {
      estado: filter,
      examen: examId,
      orden,
    },
  })
}

/** Detalle de una incidencia. `reportId` es `estudiante-123`. */
export async function getIncident(
  reportId: string,
  signal?: AbortSignal
): Promise<IncidentDetailResponse> {
  return apiClient<IncidentDetailResponse>(`/docente/incidencias/${reportId}`, {
    signal,
  })
}

/** Aprueba la incidencia. La observación es opcional. */
export async function approveIncident(
  reportId: string,
  observacion: string | null
): Promise<IncidentDetailResponse> {
  return apiPost<IncidentDetailResponse>(
    `/docente/incidencias/${reportId}/aprobar`,
    { observacion }
  )
}

/** Rechaza la incidencia. La observación es obligatoria. */
export async function rejectIncident(
  reportId: string,
  observacion: string
): Promise<IncidentDetailResponse> {
  return apiPost<IncidentDetailResponse>(
    `/docente/incidencias/${reportId}/rechazar`,
    { observacion }
  )
}

/**
 * Descarga la evidencia con el token; el endpoint es autenticado.
 *
 * `apiClient` con `responseType: 'blob'` entrega el archivo. `evidenceUrl` es
 * relativo a `/api` (lo devuelve el backend en `incidencia.evidencia.url`).
 */
export function getIncidentEvidence(
  reportId: string,
  evidenceUrl: string,
  signal?: AbortSignal
): Promise<Blob> {
  return apiClient<Blob>(evidenceUrl, { responseType: 'blob', signal })
}
