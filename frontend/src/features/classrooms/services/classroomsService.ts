import { apiClient, apiPost } from '@/lib/api-client'
import type {
  Classroom,
  ClassroomFormState,
  ClassroomListResponse,
  ClassroomResponse
} from '../types/classroom.types'

/**
 * Obtiene el catálogo institucional de ambientes.
 */
export async function getClassrooms(signal?: AbortSignal): Promise<Classroom[]> {
  const response = await apiClient<ClassroomListResponse>('/ambientes', {
    signal
  })

  return response.data
}

/**
 * Registra un nuevo ambiente en el catálogo institucional.
 */
export async function createClassroom(data: ClassroomFormState): Promise<Classroom> {
  const response = await apiPost<ClassroomResponse>('/ambientes', {
    nro_aula: data.nro_aula.trim(),
    capacidad: Number(data.capacidad),
    ubicacion: data.ubicacion.trim()
  })

  return response.data
}
