import { apiClient } from '@/lib/api-client'
import type {
  FaultType,
  RiskCenterFilters,
  RiskCenterListResponse,
  RiskCenterPage,
  RiskCenterRecordDetail,
} from '../types/riskCenter.types'

export const RISK_CENTER_PER_PAGE = 10

/**
 * Registros de la Central de Riesgo, del más reciente al más antiguo.
 *
 * La búsqueda sin tildes y el filtro de tipo los resuelve el servidor; la vista solo manda lo que
 * el usuario eligió.
 */
export async function getRiskCenterRecords(
  { q, tipo, page }: RiskCenterFilters,
  signal?: AbortSignal
): Promise<RiskCenterPage> {
  const response = await apiClient<RiskCenterListResponse>('/central-riesgo', {
    query: {
      q: q === '' ? undefined : q,
      tipo: tipo ?? undefined,
      page,
      per_page: RISK_CENTER_PER_PAGE,
    },
    signal,
  })

  return {
    items: response.data,
    page: response.meta.current_page,
    perPage: response.meta.per_page,
    total: response.meta.total,
    totalPages: Math.max(1, response.meta.last_page),
  }
}

export async function getRiskCenterRecord(
  id: number,
  signal?: AbortSignal
): Promise<RiskCenterRecordDetail> {
  const response = await apiClient<{ data: RiskCenterRecordDetail }>(`/central-riesgo/${id}`, { signal })

  return response.data
}

/** Catálogo compartido de tipos de falta, para el filtro. */
export async function getFaultTypes(signal?: AbortSignal): Promise<FaultType[]> {
  const response = await apiClient<{ data: FaultType[] }>('/tipos-falta', { signal })

  return response.data
}

/** La evidencia solo se entrega con la sesión: se pide como archivo, no se enlaza por URL pública. */
export function getRiskCenterEvidence(id: number, signal?: AbortSignal): Promise<Blob> {
  return apiClient<Blob>(`/central-riesgo/${id}/evidencia`, { responseType: 'blob', signal })
}
