//=======================================================================
// HU-057-Situacion Excepcional
//=======================================================================
export type SituationType = 'CAMBIO_AMBIENTE' | 'IDENTIFICACION'

export interface SituationRoom {
  id_ambiente: number
  nro_aula: string
}

export interface ExamSituationContext {
  examId: number
  examName: string
  student: { id_estudiante: number; cod_sis: string; nombre_completo: string }
  room: SituationRoom
  rooms: SituationRoom[]
  /** Hora del servidor en ISO 8601, recibida del estado de En curso. */
  serverTime: string
}

export interface SituationInput {
  type: SituationType
  reason: string
  destinationRoomId: string
}

export interface ExamSituation {
  id_situacion: number
  tipo: SituationType
  motivo: string
  registrado_en: string
  estudiante: ExamSituationContext['student']
  ambiente_origen: SituationRoom
  ambiente_destino: SituationRoom | null
  registrante: { id_usuario: string; nombre_completo: string }
}

export interface SituationCreated {
  data: ExamSituation
  mensaje: string
}

export interface SituationPage {
  data: ExamSituation[]
  meta: { current_page: number; last_page: number; total: number }
}

export type SituationErrors = Partial<Record<
  'tipo' | 'motivo' | 'id_ambiente_destino', string
>>

export const SITUATION_LABELS: Record<SituationType, string> = {
  CAMBIO_AMBIENTE: 'Cambio de ambiente',
  IDENTIFICACION: 'Problema de identificación',
}

export function formatSituationTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Hora no disponible'
  return new Intl.DateTimeFormat('es-BO', {
    timeZone: 'America/La_Paz', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).format(date)
}

export interface SituationExam {
  id_examen: number
  nombre_examen: string
  fecha: string
  estado: 'EN_CURSO' | 'FINALIZADO'
}

export interface SituationExamsPage {
  data: SituationExam[]
  meta: SituationPage['meta']
}

export interface SituationStudent {
  id_estudiante: number
  cod_sis: string
  nombre_completo: string
  estado_ingreso: 'INGRESO' | 'CON_RETRASO'
  ambiente: SituationRoom
  situaciones: {
    identidad_observada: boolean
    ultimo_cambio_ambiente: null | {
      id_situacion: number
      registrado_en: string
      ambiente_origen: SituationRoom
      ambiente_destino: SituationRoom
    }
  }
}

export interface SituationWorkspace {
  examen: SituationExam
  ambiente_control: number | null
  ambientes: SituationRoom[]
  hora_servidor: string
  estudiantes: SituationStudent[]
  paginacion: SituationPage['meta']
}

export interface SituationWorkspaceFilters {
  page?: number
  id_ambiente?: number
  id_estudiante?: number
  q?: string
}