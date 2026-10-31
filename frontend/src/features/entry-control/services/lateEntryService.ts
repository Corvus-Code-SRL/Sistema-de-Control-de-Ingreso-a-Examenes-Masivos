import { apiClient, apiPost } from '@/lib/api-client'
import type {
  LateEntryActionInput, LateEntryRegistration, LateEntryRequest, LateEntryVerification, LateEntryVerificationInput,
} from '../types/late-entry.types'

interface DataResponse<T> { data: T }
const base = (examId: number) => `/control-ingreso/examenes/${examId}/retrasos`

/** Ingreso con retraso de un examen EN_CURSO (HU-037). */
export const lateEntryService = {
  async verify(examId: number, input: LateEntryVerificationInput): Promise<LateEntryVerification> {
    const response = await apiPost<DataResponse<LateEntryVerification>>(`${base(examId)}/verificar`, input)
    return response.data
  },

  async list(examId: number, signal?: AbortSignal): Promise<LateEntryRequest[]> {
    const response = await apiClient<DataResponse<LateEntryRequest[]>>(`${base(examId)}/solicitudes`, { signal })
    return response.data
  },

  async request(examId: number, input: LateEntryActionInput): Promise<LateEntryRequest> {
    const response = await apiPost<DataResponse<LateEntryRequest>>(`${base(examId)}/solicitudes`, input)
    return response.data
  },

  async approve(examId: number, requestId: number): Promise<LateEntryRequest> {
    const response = await apiPost<DataResponse<LateEntryRequest>>(
      `${base(examId)}/solicitudes/${requestId}/autorizar`, {}
    )
    return response.data
  },

  async reject(examId: number, requestId: number): Promise<LateEntryRequest> {
    const response = await apiPost<DataResponse<LateEntryRequest>>(
      `${base(examId)}/solicitudes/${requestId}/rechazar`, {}
    )
    return response.data
  },

  async register(examId: number, input: LateEntryActionInput): Promise<LateEntryRegistration> {
    const response = await apiPost<DataResponse<LateEntryRegistration>>(`${base(examId)}/registrar`, input)
    return response.data
  },
}
