import { apiClient } from '@/lib/api-client'
import type { Faculty, FacultyListResponse } from '../types/alienAttempts.types'

/** Catálogo de facultades activas para el filtro del reporte. */
export async function getFaculties(signal?: AbortSignal): Promise<Faculty[]> {
  const response = await apiClient<FacultyListResponse>('/facultades', { signal })

  return response.data
}
