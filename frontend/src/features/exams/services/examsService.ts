import { apiClient, apiPost } from '@/lib/api-client';
import { Exam, ExamFormOptions, CreateExamPayload } from '../types/exams.types';

interface DataResponse<T> {
  data: T;
}

export const examsService = {
  async getFormData(): Promise<ExamFormOptions> {
    const response = await apiClient<DataResponse<ExamFormOptions>>('/exams/form-data');
    return response.data;
  },

  async createExam(payload: CreateExamPayload): Promise<Exam> {
    const response = await apiPost<DataResponse<Exam>>('/exams', payload);
    return response.data;
  },
};
