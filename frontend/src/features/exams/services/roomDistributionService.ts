import { apiClient, apiPost, apiPut } from '@/lib/api-client'
import type {
  DistributionExamOption, DistributionProposal, RoomDistributionView, SaveDistributionPayload,
} from '../types/roomDistribution.types'

interface DataResponse<T> { data: T; mensaje?: string }
const distributionPath = (examId: number): string => `/examenes/${examId}/distribucion`

export const roomDistributionService = {
  async getExams(signal?: AbortSignal): Promise<DistributionExamOption[]> {
    return (await apiClient<DataResponse<DistributionExamOption[]>>('/examenes/distribucion', { signal })).data
  },
  async getDistribution(examId: number, signal?: AbortSignal): Promise<RoomDistributionView> {
    return (await apiClient<DataResponse<RoomDistributionView>>(distributionPath(examId), { signal })).data
  },
  async propose(examId: number, signal?: AbortSignal): Promise<DistributionProposal> {
    return (await apiPost<DataResponse<DistributionProposal>>(
      `${distributionPath(examId)}/propuesta`, {}, { signal },
    )).data
  },
  async save(examId: number, payload: SaveDistributionPayload, signal?: AbortSignal): Promise<RoomDistributionView> {
    return (await apiPut<DataResponse<RoomDistributionView>>(distributionPath(examId), payload, { signal })).data
  },
}
