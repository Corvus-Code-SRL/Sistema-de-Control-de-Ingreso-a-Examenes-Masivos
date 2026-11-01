import { apiClient } from '@/lib/api-client'
import type { HistoryList, HistorySummary } from '../types/examHistory.types'

interface DataResponse<T> { data: T }

export const examHistoryService = {
  async list(filters: { materia: string; buscar: string; page: string }, signal?: AbortSignal): Promise<HistoryList> {
    const params = new URLSearchParams()
    if (filters.materia) params.set('materia', filters.materia)
    if (filters.buscar) params.set('buscar', filters.buscar)
    if (filters.page !== '1') params.set('page', filters.page)
    const query = params.toString()
    const response = await apiClient<DataResponse<HistoryList>>(`/historial/examenes${query ? `?${query}` : ''}`, { signal })
    return response.data
  },
  async show(id: number, signal?: AbortSignal): Promise<HistorySummary> {
    const response = await apiClient<DataResponse<HistorySummary>>(`/historial/examenes/${id}`, { signal })
    return response.data
  }
}
