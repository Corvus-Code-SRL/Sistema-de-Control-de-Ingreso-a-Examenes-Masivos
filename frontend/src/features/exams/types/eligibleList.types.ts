import type { ExamStatus } from './exams.types'

export type EligibilityStatus = 'HABILITADO' | 'NO_HABILITADO' | 'CASO_ESPECIAL'

/** Motivos manuales de versiones anteriores: ya no se pueden elegir, pero se siguen mostrando. */
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

/** Motivo fijo que el sistema asigna a quien no figura en el archivo; el docente no lo elige. */
export const NOT_IN_FILE_REASON = 'NO_FIGURA_EN_LISTA'

export type StoredReason = NoEligibleReason | typeof NOT_IN_FILE_REASON

export const STORED_REASON_LABELS: Record<StoredReason, string> = {
  ...NO_ELIGIBLE_REASONS,
  [NOT_IN_FILE_REASON]: 'No figura en la lista cargada',
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
  motivo: StoredReason | null
  detalle: string | null
}

export interface EligibleListData {
  editable: boolean
  estado: ExamStatus
  estudiantes: EligibleStudent[]
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

export interface NotEnabledByUpload {
  id_estudiante: number
  codigo_sis: string
  nombre_completo: string
}

/** Resultado de reemplazar la lista con el archivo: ya está guardado en el servidor. */
export interface UploadConfirmData {
  total_filas: number
  filas_inconsistentes: number
  no_encontrados: number
  habilitados: number
  total_no_habilitados: number
  motivo: typeof NOT_IN_FILE_REASON
  no_habilitados: NotEnabledByUpload[]
}

// --- Client-side State Types ---

export type EligibilityFilter = 'todos' | 'habilitados' | 'no_habilitados'
