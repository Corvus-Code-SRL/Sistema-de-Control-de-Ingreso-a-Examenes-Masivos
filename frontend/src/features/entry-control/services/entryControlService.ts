import { apiClient, apiPost } from '@/lib/api-client'
import type {
  EntryActionInput, EntryContext, EntryStatus, OpenEntryExam, PendingEntryStatus, RejectionReason, StatusResponse,
  StudentMatch, Verification, VerificationInput,
} from '../types/entry-control.types'

interface DataResponse<T> { data: T }
const base = (examId: number) => `/control-ingreso/examenes/${examId}`

export const entryControlService = {
  async openExams(signal?: AbortSignal): Promise<OpenEntryExam[]> {
    const response = await apiClient<DataResponse<OpenEntryExam[]>>('/control-ingreso/examenes', { signal })
    return response.data
  },

  async context(examId: number, signal?: AbortSignal): Promise<EntryContext> {
    const response = await apiClient<DataResponse<EntryContext>>(`${base(examId)}/contexto`, { signal })
    return response.data
  },

  async status(examId: number, version?: string, signal?: AbortSignal): Promise<StatusResponse> {
    const response = await apiClient<DataResponse<StatusResponse>>(`${base(examId)}/estado`, {
      query: { desde: version }, signal,
    })
    return response.data
  },

  async search(examId: number, name: string, signal?: AbortSignal): Promise<StudentMatch[]> {
    const response = await apiClient<DataResponse<StudentMatch[]>>(`${base(examId)}/buscar`, {
      query: { nombre: name }, signal,
    })
    return response.data
  },

  async searchByIdentifier(
    examId: number,
    field: 'sis' | 'ci',
    prefix: string,
    signal?: AbortSignal
  ): Promise<StudentMatch[]> {
    const response = await apiClient<DataResponse<StudentMatch[]>>(`${base(examId)}/buscar`, {
      query: { [field]: prefix }, signal,
    })
    return response.data
  },

  async verify(examId: number, input: VerificationInput): Promise<Verification> {
    const response = await apiPost<DataResponse<Verification>>(`${base(examId)}/verificar`, input)
    return response.data
  },

  async confirm(examId: number, input: EntryActionInput): Promise<void> {
    await apiPost(`${base(examId)}/confirmar-ingreso`, input)
  },

  async reject(examId: number, input: EntryActionInput & {
    motivo: RejectionReason; observacion?: string
  }): Promise<{ id_intento: number }> {
    const response = await apiPost<DataResponse<{ id_intento: number }>>(`${base(examId)}/rechazar-ingreso`, input)
    return response.data
  },
}

export function isFullStatus(value: StatusResponse): value is EntryStatus {
  return 'ingresados' in value
}

export function isPendingStatus(value: StatusResponse): value is PendingEntryStatus {
  return 'estado' in value && value.estado === 'PROGRAMADO'
}
