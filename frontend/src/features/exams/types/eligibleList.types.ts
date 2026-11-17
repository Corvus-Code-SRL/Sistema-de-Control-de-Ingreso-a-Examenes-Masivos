import type { ExamStatus } from './exams.types'

export type EligibilityStatus = 'HABILITADO' | 'NO_HABILITADO' | 'CASO_ESPECIAL'

export type NoEligibleReason =
  | 'ASISTENCIA_INSUFICIENTE'
  | 'NO_ENTREGO_TRABAJO_FINAL'
  | 'NO_CUMPLE_PRERREQUISITO'
  | 'OTRO'

export const NO_ELIGIBLE_REASONS: Record<NoEligibleReason, string> = {
  ASISTENCIA_INSUFICIENTE: 'Asistencia insuficiente',
  NO_ENTREGO_TRABAJO_FINAL: 'No entregó el trabajo final',
  NO_CUMPLE_PRERREQUISITO: 'No cumple prerrequisito',
  OTRO: 'Otro (especificar)',
}

// --- API Responses ---

export interface ExamSelectorRef {
  id: number
  nombre: string
}

export interface ExamSelectorItem {
  id_examen: number
  nombre: string
  materia: ExamSelectorRef
  carrera: ExamSelectorRef
  fecha: string
  hora_inicio: string
  estado: Extract<ExamStatus, 'PROGRAMADO' | 'EN_CURSO'>
  grupos: string[]
}

export interface EligibleStudent {
  id_estudiante: number
  codigo_sis: string
  nombre_completo: string
  id_grupo: number
  nombre_grupo: string
  estado_habilitacion: EligibilityStatus
  caso_especial: boolean
  motivo: NoEligibleReason | null
  detalle: string | null
}

export interface EligibleListData {
  editable: boolean
  estado: ExamStatus
  estudiantes: EligibleStudent[]
}

// --- API Payloads ---

export interface EligibleChange {
  id_estudiante: number
  estado_habilitacion: EligibilityStatus
  motivo?: NoEligibleReason
  detalle?: string
}

export interface UpdateEligibleListPayload {
  cambios: EligibleChange[]
}

// --- File Upload Types ---

export type PreviewRowState = 'matched' | 'not_found_in_exam' | 'inconsistent'

export interface PreviewRow {
  numero_fila: number | null
  codigo_sis: string | null
  apellidos: string | null
  nombres: string | null
  estado: PreviewRowState
  errores: string[]
  nombre_estudiante?: string | null
  nombre_grupo?: string | null
}

export interface UploadPreviewData {
  token: string
  total_filas: number
  filas_validas: number
  filas_inconsistentes: number
  faltantes: number
  filas: PreviewRow[]
}

export interface PendingChangeFromUpload {
  id_estudiante: number
  codigo_sis: string
  nombre_completo: string
  estado_habilitacion: EligibilityStatus
  motivo: NoEligibleReason | null
  detalle: string | null
}

export interface UploadConfirmData {
  total_filas: number
  filas_inconsistentes: number
  no_encontrados: number
  cambios_pendientes: PendingChangeFromUpload[]
}

// --- Client-side State Types ---

export type PendingChange = Omit<EligibleChange, 'id_estudiante'>

export type PendingChangesMap = Map<number, PendingChange>

export type EligibilityFilter = 'todos' | 'habilitados' | 'no_habilitados'
