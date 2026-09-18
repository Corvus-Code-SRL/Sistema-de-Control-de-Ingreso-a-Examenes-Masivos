import { apiClient, ApiResponse } from '@/lib/api-client';
import { Exam, ExamFormOptions, CreateExamPayload } from '../types/exams.types';

export const examsService = {
  async getFormData(): Promise<ExamFormOptions> {
    const response = await apiClient.get<ApiResponse<ExamFormOptions>>('/exams/form-data');
    return response.data;
  },

  async createExam(payload: CreateExamPayload): Promise<Exam> {
    const response = await apiClient.post<ApiResponse<Exam>>('/exams', payload);
    return response.data;
  },
};
