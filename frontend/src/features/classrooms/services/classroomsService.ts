import { apiClient } from '@/lib/api-client'
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
  const response = await fetch('/api/ambientes', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json'
    },
    body: JSON.stringify({
      nro_aula: data.nro_aula.trim(),
      capacidad: Number(data.capacidad),
      ubicacion: data.ubicacion.trim()
    })
  })

  const text = await response.text()

  let result: any = null

  if (text) {
    try {
      result = JSON.parse(text)
    } catch {
      result = null
    }
  }

  if (!response.ok) {
    if (response.status === 422 && result?.errors) {
      throw result
    }

    throw {
      message: result?.message ?? 'No se pudo registrar el ambiente. Intente nuevamente.'
    }
  }

  if (!result?.data) {
    throw {
      message: 'El servidor devolvió una respuesta inesperada.'
    }
  }

  return result.data as Classroom
}
