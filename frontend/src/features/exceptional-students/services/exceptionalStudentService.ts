import { apiClient, apiPost } from '@/lib/api-client'
import type {
  ExceptionalCandidate,
  ExceptionalExam,
  ExceptionalOptions,
  ExceptionalRegistration,
  RegisterExceptionalInput
} from '../types/exceptional-student.types'

/** Registro de estudiantes excepcionales (HU-023) mediante el cliente autenticado compartido. */
export const exceptionalStudentService = {
  /** Exámenes En curso del docente responsable. */
  exams(signal?: AbortSignal): Promise<{ data: ExceptionalExam[] }> {
    return apiClient('/docente/estudiantes-excepcionales/examenes', { signal })
  },

  /** Busca por código SIS en los registros de SCIEM; un 404 es «no se encontró». */
  search(examId: number, sisCode: string, signal?: AbortSignal) {
    return apiClient<{ data: ExceptionalCandidate }>(
      `/docente/examenes/${examId}/estudiantes-excepcionales/buscar`,
      { query: { cod_sis: sisCode }, signal }
    )
  },

  options(examId: number, signal?: AbortSignal): Promise<{ data: ExceptionalOptions }> {
    return apiClient(`/docente/examenes/${examId}/estudiantes-excepcionales/opciones`, { signal })
  },

  register(examId: number, input: RegisterExceptionalInput): Promise<ExceptionalRegistration> {
    return apiPost<ExceptionalRegistration>(
      `/docente/examenes/${examId}/estudiantes-excepcionales`,
      {
        id_estudiante: input.studentId,
        motivo: input.reason.trim(),
        id_grupo: input.groupId,
        id_carrera: input.careerId,
        id_ambiente: input.roomId
      }
    )
  }
}
