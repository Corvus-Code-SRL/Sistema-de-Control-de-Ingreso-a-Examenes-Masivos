import { apiClient, apiPost } from '@/lib/api-client'
import type {
  EligibleListData,
  ExamSelectorItem,
  UploadConfirmData,
  UploadPreviewData,
} from '../types/eligibleList.types'

interface DataResponse<T> {
  data: T
  mensaje?: string
}

export const eligibleListService = {
  async getExams(signal?: AbortSignal): Promise<ExamSelectorItem[]> {
    const response = await apiClient<DataResponse<ExamSelectorItem[]>>(
      '/examenes/habilitados',
      { signal }
    )
    return response.data
  },

  async getEligibleList(examId: number, signal?: AbortSignal): Promise<EligibleListData> {
    const response = await apiClient<DataResponse<EligibleListData>>(
      `/examenes/${examId}/habilitados`,
      { signal }
    )
    return response.data
  },

  async previewUpload(
    examId: number,
    file: File,
    signal?: AbortSignal
  ): Promise<UploadPreviewData> {
    const body = new FormData()
    body.append('archivo', file)

    const response = await apiPost<DataResponse<UploadPreviewData>>(
      `/examenes/${examId}/habilitados/preview`,
      body,
      { signal }
    )
    return response.data
  },

  async confirmUpload(
    examId: number,
    token: string,
    signal?: AbortSignal
  ): Promise<UploadConfirmData> {
    const response = await apiPost<DataResponse<UploadConfirmData>>(
      `/examenes/${examId}/habilitados/confirm`,
      { token },
      { signal }
    )
    return response.data
  },
}

export const MAX_ELIGIBLE_FILE_BYTES = 10 * 1024 * 1024
export const ALLOWED_ELIGIBLE_EXTENSIONS = ['csv', 'xlsx'] as const

export function validateEligibleFile(file: File): string | null {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''

  if (!ALLOWED_ELIGIBLE_EXTENSIONS.some((allowed) => allowed === extension)) {
    return 'El archivo debe tener formato CSV o XLSX.'
  }

  if (file.size === 0) {
    return 'El archivo está vacío.'
  }

  if (file.size > MAX_ELIGIBLE_FILE_BYTES) {
    return 'El archivo no puede superar los 10 MB.'
  }

  return null
}
