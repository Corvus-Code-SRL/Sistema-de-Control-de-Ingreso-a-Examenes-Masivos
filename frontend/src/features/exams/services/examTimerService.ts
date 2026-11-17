import { apiClient } from '@/lib/api-client'
import type { ExamStatus } from '../types/exams.types'

export interface ExamTimer {
  id_examen: number
  nombre_examen: string
  fecha: string
  hora_inicio: string
  hora_fin: string | null
  duracion: number | null
  estado: ExamStatus
  materia?: { nombre: string } | null
  carrera?: { nombre: string } | null
  fin_permitido_en: string | null
  cronometro_iniciado_en: string | null
  cronometro_vence_en: string | null
}

export interface ExamTimerDetail {
  examen: ExamTimer
  hora_servidor_en: string
}

interface ExamTimersResponse {
  examenes: ExamTimer[]
  hora_servidor_en: string
}

function timerPath(examId: number) {
  return `/examenes/${examId}/cronometro`
}

export const examTimerService = {
  async list(signal?: AbortSignal) {
    const { data } = await apiClient<{ data: ExamTimersResponse }>('/examenes/cronometros', { signal })
    return data
  },
  async get(examId: number, signal: AbortSignal) {
    const { data } = await apiClient<{ data: ExamTimerDetail }>(timerPath(examId), { signal })
    return data
  },
  async save(examId: number, minutes: number, adjust: boolean, signal: AbortSignal) {
    const { data } = await apiClient<{ data: ExamTimerDetail }>(timerPath(examId), {
      method: adjust ? 'PUT' : 'POST',
      body: { minutos: minutes },
      signal,
    })
    return data
  },
}
