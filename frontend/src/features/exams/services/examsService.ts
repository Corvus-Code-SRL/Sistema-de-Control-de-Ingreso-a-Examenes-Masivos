import { apiClient, apiPost } from '@/lib/api-client';
import { Exam, ExamFormOptions, CreateExamPayload } from '../types/exams.types';

interface DataResponse<T> {
  data: T;
  mensaje?: string;
}

export const examsService = {
  async getFormData(): Promise<ExamFormOptions> {
    const response = await apiClient<DataResponse<ExamFormOptions>>('/examenes/formulario');
    return response.data;
  },

  async createExam(payload: CreateExamPayload): Promise<Exam> {
    const response = await apiPost<DataResponse<Exam>>('/examenes', payload);
    return response.data;
  },
};
