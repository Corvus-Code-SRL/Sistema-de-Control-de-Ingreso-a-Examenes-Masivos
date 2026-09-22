import { apiClient } from '@/lib/api-client'
import type { PeriodOption } from '../types/group.types'

interface PeriodsResponse {
  data: PeriodOption[]
  meta: { id_periodo_activo: number | null }
}

export interface Periods {
  periods: PeriodOption[]
  activePeriodId: number | null
}

/** Períodos disponibles para el selector, con el activo identificado (CA 11). */
export async function getPeriods(signal?: AbortSignal): Promise<Periods> {
  const response = await apiClient<PeriodsResponse>('/periodos', { signal })

  return {
    periods: response.data,
    activePeriodId: response.meta.id_periodo_activo,
  }
}