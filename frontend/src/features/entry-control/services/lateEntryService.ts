import { ApiError, apiClient, apiPost } from '@/lib/api-client'
import { throttleWaitSeconds } from '@/lib/throttle'
import { LATE_ENTRY_MESSAGES } from '../components/lateEntryMessages'
import type { StudentMatch, StudentSearchSource } from '../types/entry-control.types'
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

  async list(examId: number, signal?: AbortSignal): Promise<LateEntryRequest[]> {
    const response = await apiClient<DataResponse<LateEntryRequest[]>>(`${base(examId)}/solicitudes`, { signal })
    return response.data
  },

  async request(examId: number, input: LateEntryActionInput): Promise<LateEntryRequest> {
    const response = await apiPost<DataResponse<LateEntryRequest>>(`${base(examId)}/solicitudes`, input)
    return response.data
  },

  /** `confirmOtherExam`: el docente autoriza con la advertencia de otro examen a la vista. */
  async approve(examId: number, requestId: number, confirmOtherExam = false): Promise<LateEntryRequest> {
    const response = await apiPost<DataResponse<LateEntryRequest>>(
      `${base(examId)}/solicitudes/${requestId}/autorizar`, confirmOtherExam ? { confirmar_otro_examen: true } : {}
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

/**
 * Las sugerencias muestran el mensaje del error tal cual; ante un 429 (RNF-03) se cambia por el
 * pedido de esperar, sin tocar los componentes de búsqueda de HU-032.
 */
function withThrottleNotice(cause: unknown): never {
  if (cause instanceof ApiError && cause.isTooManyRequests) {
    throw new ApiError(cause.status, LATE_ENTRY_MESSAGES.throttled(throttleWaitSeconds(cause)), {}, undefined, {
      retryAfter: cause.retryAfter,
    })
  }
  throw cause
}

/** Sugerencias de la identificación durante EN_CURSO, cuando la búsqueda de HU-032 ya no está disponible. */
export const LATE_ENTRY_SEARCH: StudentSearchSource = {
  byName: (examId, name, signal) => lateEntryService.search(examId, name, signal).catch(withThrottleNotice),
  byIdentifier: (examId, field, prefix, signal) =>
    lateEntryService.searchByIdentifier(examId, field, prefix, signal).catch(withThrottleNotice),
}
