import { apiClient } from '@/lib/api-client';
import type { ExamInProgress } from '../types/examInProgress.types';

interface DataResponse<T> {
  data: T;
}

export const examInProgressService = {
  async get(
    examId: number,
    signal?: AbortSignal
  ): Promise<ExamInProgress> {
    const response = await apiClient<DataResponse<ExamInProgress>>(
      `/examenes/${examId}/en-curso`,
      { signal }
    );

    return response.data;
  },
};
