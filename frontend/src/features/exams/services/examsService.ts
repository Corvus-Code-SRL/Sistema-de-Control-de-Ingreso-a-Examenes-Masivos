import { apiClient, apiPost, apiPut } from '@/lib/api-client';
import {
  AssignGroupsPayload,
  CreateExamPayload,
  Exam,
  ExamFormOptions,
  UpdateExamPayload,
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

  async listExams(signal?: AbortSignal): Promise<Exam[]> {
    const response = await apiClient<DataResponse<Exam[]>>('/examenes', { signal });
    return response.data;
  },

  /**
   * Vista Programados: solo los exámenes vigentes (PROGRAMADO o EN_INGRESO del período activo,
   * incluido el recién creado que aún no tiene grupos), del más próximo al más lejano. Lo decide
   * el backend con `?vista=programados`.
   */
  async listScheduledExams(signal?: AbortSignal): Promise<Exam[]> {
    const response = await apiClient<DataResponse<Exam[]>>('/examenes?vista=programados', { signal });
    return response.data;
  },

  async getExam(examId: number, signal?: AbortSignal): Promise<Exam> {
    const response = await apiClient<DataResponse<Exam>>(`/examenes/${examId}`, { signal });
    return response.data;
  },

  async createExam(payload: CreateExamPayload): Promise<Exam> {
    const response = await apiPost<DataResponse<Exam>>('/examenes', payload);
    return response.data;
  },

  async updateExam(examId: number, payload: UpdateExamPayload): Promise<Exam> {
    const response = await apiPut<DataResponse<Exam>>(`/examenes/${examId}`, payload);
    return response.data;
  },

  async cancelExam(examId: number): Promise<Exam> {
    const response = await apiPost<DataResponse<Exam>>(`/examenes/${examId}/cancelar`, {});
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
