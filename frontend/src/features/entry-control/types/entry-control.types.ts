export interface EntryRoom {
  id_ambiente: number
  nro_aula: string
}

export interface EntryContext {
  id_examen: number
  nombre_examen: string
  fecha: string | null
  hora_inicio: string | null
  hora_fin: string | null
  estado: string
  materia: string | null
  carrera: string | null
  grupos: string[]
  rol_controlador: 'DOCENTE' | 'AUXILIAR'
  id_ambiente_asignado: number | null
  ambientes: EntryRoom[]
}

export interface StudentMatch {
  id_estudiante: number
  cod_sis: string
  nombre_completo: string
}

export interface EntryStudent extends StudentMatch {
  ci: string | null
  ci_pendiente: boolean
  carrera: string | null
  foto_url: string | null // Reservado para una futura fuente de fotografías.
}

export interface PreviousEntry {
  registrado_en: string | null
  hora_ingreso: string | null
  id_ambiente: number | null
  nro_aula: string | null
  controlador: string | null
}

export interface Verification {
  veredicto: string
  motivo: string
  autorizado: boolean
  estudiante: EntryStudent | null
  grupo: { id_grupo: number; num_grupo: string } | null
  ambiente_asignado: EntryRoom | null
  antecedentes: { tiene_antecedentes: boolean; cantidad: number; resumen: string | null }
  ingreso_previo: PreviousEntry | null
}

export interface RecentEntry {
  id_estudiante: number
  cod_sis: string
  nombre: string
  apellido_paterno: string | null
  apellido_materno: string | null
  hora_ingreso: string | null
  registrado_en: string | null
  nro_aula: string | null
  controlador_nombre: string | null
  controlador_apellido: string | null
}

export interface EntryStatus {
  ingresados: number
  pendientes: number
  total: number
  ultimos_ingresos: RecentEntry[]
  version: string
  conectados?: unknown[]
}

export type StatusResponse = EntryStatus | {
  sin_cambios: true
  version: string
  conectados?: unknown[]
}

export type RejectionReason = 'DOCUMENTO_NO_VALIDO' | 'IDENTIDAD_DUDOSA' | 'DECISION_CONTROLADOR' | 'OTRO'

export interface VerificationInput {
  cod_sis: string
  ci?: string
  id_ambiente: number
}

export interface EntryActionInput {
  id_estudiante: number
  ci?: string
  id_ambiente: number
}

export interface OpenEntryExam {
  id_examen: number
  estado: 'PROGRAMADO' | 'EN_INGRESO'
  nombre_examen: string
  fecha: string | null
  hora_inicio: string | null
  materia: string | null
  ambientes: string[]
}
