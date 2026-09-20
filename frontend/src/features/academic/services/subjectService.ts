import { SubjectListResponse, SubjectGroupsResponse } from '../types/subject'

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

export async function fetchSubjectCareers(): Promise<SubjectListResponse> {
  const res = await fetch(`${API_BASE}/materias`, {
    headers: { Accept: 'application/json' }
  })
  if (!res.ok) throw new Error('Error al cargar el catálogo de materias.')
  return res.json()
}

export async function fetchGroupsForPair(
  careerId: number,
  subjectId: number
): Promise<SubjectGroupsResponse> {
  const res = await fetch(`${API_BASE}/carreras/${careerId}/materias/${subjectId}/grupos`, {
    headers: { Accept: 'application/json' }
  })
  if (!res.ok) {
    const body = await res.json().catch(() => null)
    throw new Error(body?.message ?? 'Esa materia no está disponible.')
  }
  return res.json()
}
