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
  ingreso_ordinario_cerrado?: boolean
  materia: string | null
  carrera: string | null
  grupos: string[]
  rol_controlador: 'DOCENTE' | 'AUXILIAR'
  es_creador: boolean
  id_ambiente_asignado: number | null
  ambientes: EntryRoom[]
}

export interface StudentMatch {
  id_estudiante: number
  cod_sis: string
  nombre_completo: string
  ci?: string | null
}

export interface EntryStudent extends StudentMatch {
  ci: string | null
  ci_pendiente: boolean
  carrera: string | null
  foto_url: string | null
}

export interface EntryAssistant {
  cod_sis: string
  nombre_completo: string
  ambiente_asignado: EntryRoom | null
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
  id_intento?: number | null
  registrado_en?: string | null
  reportado?: boolean
  motivo: string
  autorizado: boolean
  estudiante: EntryStudent | null
  auxiliar?: EntryAssistant | null
  /** `docente` solo viene si la lista del examen va separada por grupo (HU-027). */
  grupo: { id_grupo: number; num_grupo: string; docente?: string | null } | null
  /** Agregado solo a la lista de este examen (HU-023), con el motivo del registro. */
  es_excepcional?: boolean
  motivo_excepcional?: string | null
  ambiente_asignado: EntryRoom | null
  antecedentes: { total: number; latest: { tipo_falta: string; fecha_registro: string } | null }
  ingreso_previo: PreviousEntry | null
  reporte_pendiente?: boolean
}

/** Textos de las acciones del resultado de verificación según el flujo que lo usa. */
export interface VerificationActions {
  confirmLabel: string
  busyLabel: string
  rejectLabel: string
  /** El resultado negativo deja un intento registrado (solo el control normal de HU-032). */
  recordsAttempt: boolean
}

export interface RecentEntry {
  id_estudiante: number
  /** Grupo por el que figura en la nómina (para la lista separada por grupo). */
  id_grupo?: number | null
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

/** Avance de un grupo del examen cuando la lista va separada por grupo (HU-027). */
export interface EntryGroupProgress {
  id_grupo: number
  num_grupo: string
  docente: string | null
  total: number
  ingresados: number
}

export interface EntryStatus {
  ingresados: number
  pendientes: number
  total: number
  ultimos_ingresos: RecentEntry[]
  /** Solo si quien invitó pidió la lista separada por grupo; con la lista junta no viene. */
  por_grupo?: EntryGroupProgress[]
  version: string
  conectados?: unknown[]
}

export interface EntryOpeningFailure {
  codigo: 'SIN_NOMINA' | 'SIN_AMBIENTES' | 'CAPACIDAD_INSUFICIENTE'
  mensaje: string
}

export interface PendingEntryStatus {
  estado: 'PROGRAMADO'
  motivo_apertura: EntryOpeningFailure | null
}

export type StatusResponse = EntryStatus | PendingEntryStatus | {
  sin_cambios: true
  version: string
  conectados?: unknown[]
}

export type RejectionReason = 'DOCUMENTO_NO_VALIDO' | 'IDENTIDAD_DUDOSA' | 'DECISION_CONTROLADOR' | 'OTRO'

export interface VerificationInput {
  cod_sis?: string
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
  estado: 'PROGRAMADO' | 'EN_CURSO'
  control_abierto?: boolean
  nombre_examen: string
  fecha: string | null
  hora_inicio: string | null
  materia: string | null
  carrera: string | null
  ambientes: string[]
  rol_controlador: 'DOCENTE' | 'AUXILIAR'
  es_creador: boolean
  id_ambiente_asignado: number | null
  ambiente_asignado: string | null
  apertura_programada_en: string
  motivo_apertura: EntryOpeningFailure | null
  total: number
  ingresados: number
  pendientes: number
}

export interface RejectionResponse {
  registrado_en?: string
  registrado: boolean
  id_intento: number
  motivo: string
}

export interface RejectedAttempt {
  cod_sis?: string | null
  id_intento: number
  estudiante: StudentMatch | null
  ambiente: EntryRoom
  motivo: string
  observacion: string | null
  registrado_en: string
  reportado: boolean
}

export interface CurrentRejectedAttempt {
  registeredAt?: string | null
  attemptId: number
  student: StudentMatch
  roomId: number
  reason: string
  reported: boolean
}

/** De dónde salen las sugerencias de la identificación; cada flujo aporta la suya. */
export interface StudentSearchSource {
  byName: (examId: number, name: string, signal?: AbortSignal) => Promise<StudentMatch[]>
  byIdentifier: (examId: number, field: 'sis' | 'ci', prefix: string, signal?: AbortSignal) => Promise<StudentMatch[]>
}
