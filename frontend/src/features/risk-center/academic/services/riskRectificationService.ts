import { apiClient } from '@/lib/api-client'
import type {
  RectifiableRecordDetail,
  RectifiableStudent,
  RectificationInput,
  RectifiedRecord,
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

/** Registros activos y ya rectificados de un estudiante. */
export async function getStudentRecords(studentId: number, signal?: AbortSignal): Promise<StudentRecords> {
  const response = await apiClient<{ data: StudentRecords }>(`${BASE}/estudiantes/${studentId}/registros`, {
    signal,
  })

  return response.data
}

/** La ficha de un registro activo; un registro ya rectificado responde 404. */
export async function getRectifiableRecord(
  recordId: number,
  signal?: AbortSignal
): Promise<RectifiableRecordDetail> {
  const response = await apiClient<{ data: RectifiableRecordDetail }>(`${BASE}/registros/${recordId}`, {
    signal,
  })

  return response.data
}

/** Retira el registro de la Central (no se puede deshacer); responde el registro ya rectificado. */
export async function rectifyRecord(recordId: number, input: RectificationInput): Promise<RectifiedRecord> {
  const response = await apiClient<{ data: RectifiedRecord }>(`${BASE}/registros/${recordId}/rectificar`, {
    method: 'POST',
    body: input,
  })

  return response.data
}
