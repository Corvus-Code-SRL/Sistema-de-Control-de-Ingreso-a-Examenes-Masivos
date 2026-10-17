import { apiClient, apiPost } from '@/lib/api-client'
import type {
  IncidentFilter,
  IncidentListResponse,
  IncidentDetailResponse,
} from '../types/incident.types'

/**
 * Lista las incidencias del docente autenticado.
 *
 * @param filter  'pendiente' | 'aprobada' | 'rechazada' | 'todas'
 * @param examId  Opcional: filtra por examen.
 */
export async function listIncidents(
  filter: IncidentFilter,
  examId?: number,
  signal?: AbortSignal
): Promise<IncidentListResponse> {
  return apiClient<IncidentListResponse>('/docente/incidencias', {
    signal,
    query: {
      estado: filter,
      examen: examId,
    },
  })
}

/** Detalle de una incidencia. `reportId` es `estudiante-123` o `postulante-45`. */
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