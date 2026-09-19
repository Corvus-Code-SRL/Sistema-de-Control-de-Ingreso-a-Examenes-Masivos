import { apiClient, ApiResponse } from '@/lib/api-client';
import { Exam, ExamFormOptions, CreateExamPayload, Group, AssignGroupsPayload } from '../types/exams.types';

export const examsService = {
  async getFormData(): Promise<ExamFormOptions> {
    const response = await apiClient.get<ApiResponse<ExamFormOptions>>('/exams/form-data');
    return response.data;
  },

  async getAllExams(): Promise<Exam[]> {
    const response = await apiClient.get<ApiResponse<Exam[]>>('/exams');
    return response.data;
  },

  async createExam(payload: CreateExamPayload): Promise<Exam> {
    const response = await apiClient.post<ApiResponse<Exam>>('/exams', payload);
    return response.data;
  },

  async getGroupsBySubject(subjectId: number): Promise<Group[]> {
    const response = await apiClient.get<ApiResponse<Group[]>>(`/exams/materias/${subjectId}/grupos`);
    return response.data;
  },

  async assignGroups(examId: number, payload: AssignGroupsPayload): Promise<Exam> {
    const response = await apiClient.post<ApiResponse<Exam>>(`/exams/${examId}/grupos`, payload);
    return response.data;
  },
};
