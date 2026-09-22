import { apiClient, apiPost } from '@/lib/api-client';
import {
  AssignGroupsPayload,
  CreateExamPayload,
  Exam,
  ExamFormOptions,
} from '../types/exams.types';

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

  async assignGroups(examId: number, payload: AssignGroupsPayload): Promise<Exam> {
    const response = await apiPost<DataResponse<Exam>>(
      `/examenes/${examId}/grupos`,
      payload
    );
    return response.data;
  },
};
