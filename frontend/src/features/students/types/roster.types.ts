/**
 * Contrato de la carga de nómina de estudiantes.
 *
 * Reproduce lo que devuelven `POST /grupos/{id_grupo}/nomina/preview` y
 * `POST /grupos/{id_grupo}/nomina/confirm`. Ninguna de las dos respuestas trae
 * `meta`, así que no encajan en `ResourceResponse` y se declaran aquí.
 */

export type RosterRowState =
  | 'new_student'
  | 'existing_student'
  | 'already_enrolled'
  | 'inactive_enrollment'
  | 'inconsistent'

export type RosterRowError =
  | 'missing_sis_code'
  | 'missing_last_names'
  | 'missing_first_names'
  | 'sis_code_too_long'
  | 'last_names_too_long'
  | 'first_names_too_long'
  | 'duplicate_sis_code_in_file'

/** Una fila leída del archivo, tal como la clasifica el backend. */
export interface RosterPreviewRow {
  numero_fila: number
  codigo_sis: string | null
  apellidos: string | null
  nombres: string | null
  estado: RosterRowState
  errores: RosterRowError[]
}

/**
 * Resultado de la previsualización. `filas_validas` cuenta también a quienes ya
 * están en el grupo, así que la interfaz nunca lo muestra: desglosa las filas
 * por estado con `countRosterRows`.
 */
export interface RosterPreviewData {
  /** Vive 15 minutos y se consume al confirmar. */
  token: string
  total_filas: number
  filas_validas: number
  filas_inconsistentes: number
  filas: RosterPreviewRow[]
}

export interface RosterPreviewResponse {
  data: RosterPreviewData
}

/** Cifras reales de la importación: el backend vuelve a clasificar al confirmar. */
export interface RosterConfirmationData {
  total_filas: number
  filas_inconsistentes: number
  estudiantes_creados: number
  estudiantes_inscritos: number
  ya_inscritos: number
  inscripciones_inactivas: number
}

export interface RosterConfirmationResponse {
  data: RosterConfirmationData
}

/** Categoría con la que se agrupa cada inconsistencia ante el docente. */
export type RosterIssueCategory = 'Incompleto' | 'Formato' | 'Duplicado' | 'Otro'

/** Una observación ya redactada para una fila. */
export interface RosterIssue {
  category: RosterIssueCategory
  message: string
}

/**
 * Estados que el confirm incorpora al grupo.
 *
 * `already_enrolled` e `inactive_enrollment` son filas sin errores, pero el
 * backend no duplica la inscripción ni reactiva la inactiva: contarlas como
 * incorporables prometería algo que no va a pasar.
 */
export const INCORPORABLE_STATES: readonly RosterRowState[] = ['new_student', 'existing_student']

/** Límites que aplica StudentRosterRowValidator en el backend. */
export const SIS_CODE_MAX_LENGTH = 15
export const LAST_NAMES_MAX_LENGTH = 30
export const FIRST_NAMES_MAX_LENGTH = 50
