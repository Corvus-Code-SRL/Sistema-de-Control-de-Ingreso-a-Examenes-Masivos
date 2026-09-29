import type { ExamAssistant, ExamAssistantsData, ExamClassroom } from '@/features/assistants'

/** Datos de prueba de HU-09: dos ambientes del examen y dos auxiliares, uno sin ambiente. */

export const auditorio: ExamClassroom = { id_ambiente: 11, nro_aula: 'Auditorio FCyT', capacidad: 250 }

export const aula691A: ExamClassroom = { id_ambiente: 12, nro_aula: 'Aula 691A', capacidad: 60 }

export const daniela: ExamAssistant = {
  id_examen: 7,
  id_usuario: '33333333-3333-4333-8333-000000000003',
  id_ambiente: null,
  nombre_completo: 'Daniela Ferrufino Soliz',
  cod_sis: '201800451',
  ambiente: null,
}

export const maria: ExamAssistant = {
  id_examen: 7,
  id_usuario: '33333333-3333-4333-8333-000000000001',
  id_ambiente: 11,
  nombre_completo: 'María López Arnez',
  cod_sis: '201900233',
  ambiente: auditorio,
}

export function examAssistantsData(overrides: Partial<ExamAssistantsData> = {}): ExamAssistantsData {
  return {
    editable: true,
    ambientes: [auditorio, aula691A],
    auxiliares: [daniela, maria],
    ...overrides,
  }
}