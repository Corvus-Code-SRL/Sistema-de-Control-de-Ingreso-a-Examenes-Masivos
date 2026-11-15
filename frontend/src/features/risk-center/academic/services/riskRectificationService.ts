import { apiClient } from '@/lib/api-client'
import type {
  RectifiableRecordDetail,
  RectifiableStudent,
  RectificationFaultType,
  RectificationInput,
  StudentRecords,
  StudentSearchResult,
} from '../types/riskRectification.types'

const BASE = '/rectificacion-riesgo'

/** Estudiantes por código SIS, carnet o nombre; el servidor resuelve las tildes y acota la lista. */
export async function searchRectifiableStudents(
  term: string,
  signal?: AbortSignal
): Promise<StudentSearchResult> {
  const response = await apiClient<{
    data: RectifiableStudent[]
    meta: { limite: number; hay_mas: boolean }
  }>(`${BASE}/estudiantes`, { query: { q: term }, signal })

  return { students: response.data, limit: response.meta.limite, hasMore: response.meta.hay_mas }
}

export async function getStudentRecords(studentId: number, signal?: AbortSignal): Promise<StudentRecords> {
  const response = await apiClient<{ data: StudentRecords }>(`${BASE}/estudiantes/${studentId}/registros`, {
    signal,
  })

  return response.data
}

export async function getRectifiableRecord(
  recordId: number,
  signal?: AbortSignal
): Promise<RectifiableRecordDetail> {
  const response = await apiClient<{ data: RectifiableRecordDetail }>(`${BASE}/registros/${recordId}`, {
    signal,
  })

  return response.data
}

export async function getRectificationFaultTypes(signal?: AbortSignal): Promise<RectificationFaultType[]> {
  const response = await apiClient<{ data: RectificationFaultType[] }>(`${BASE}/tipos-falta`, { signal })

  return response.data
}

/** Crea la nueva versión vigente; responde el registro con su historial ya actualizado. */
export async function rectifyRecord(
  recordId: number,
  input: RectificationInput
): Promise<RectifiableRecordDetail> {
  const response = await apiClient<{ data: RectifiableRecordDetail }>(
    `${BASE}/registros/${recordId}/rectificar`,
    { method: 'POST', body: input }
  )

  return response.data
}
