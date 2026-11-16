import { apiClient, apiPost } from '@/lib/api-client'
import type {
  ExceptionalCandidate,
  ExceptionalGroup,
  ExceptionalRegistration,
  RegisterExceptionalInput
} from '../types/exceptional-student.types'

/** Registro de estudiantes excepcionales (HU-023) mediante el cliente autenticado compartido. */
export const exceptionalStudentService = {
  /** Grupos del docente en el período vigente, con sus carreras y si hoy están bloqueados. */
  groups(signal?: AbortSignal): Promise<{ data: ExceptionalGroup[] }> {
    return apiClient('/docente/estudiantes-excepcionales/grupos', { signal })
  },

  /** Busca por código SIS en los registros de SCIEM; un 404 es «no se encontró». */
  search(groupId: number, sisCode: string, signal?: AbortSignal) {
    return apiClient<{ data: ExceptionalCandidate }>(
      `/docente/grupos/${groupId}/estudiantes-excepcionales/buscar`,
      { query: { cod_sis: sisCode }, signal }
    )
  },

  register(groupId: number, input: RegisterExceptionalInput): Promise<ExceptionalRegistration> {
    return apiPost<ExceptionalRegistration>(
      `/docente/grupos/${groupId}/estudiantes-excepcionales`,
      {
        id_estudiante: input.studentId,
        motivo: input.reason.trim(),
        ...(input.careerId === undefined ? {} : { id_carrera: input.careerId })
      }
    )
  }
}
