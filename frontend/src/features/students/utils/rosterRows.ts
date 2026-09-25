import {
  FIRST_NAMES_MAX_LENGTH,
  INCORPORABLE_STATES,
  LAST_NAMES_MAX_LENGTH,
  SIS_CODE_MAX_LENGTH,
  SIS_CODE_MIN_LENGTH,
  type RosterIssue,
  type RosterPreviewData,
  type RosterPreviewRow,
  type RosterRowError,
  type RosterRowState,
} from '../types/roster.types'

/**
 * Lectura de las filas de una previsualización, sin React.
 *
 * Aquí se decide qué ve el docente de cada fila —estado, observaciones y
 * conteos— para que la tabla, las tarjetas de móvil y el paso de confirmación
 * cuenten siempre lo mismo.
 */

export type RosterStateTone = 'ok' | 'neutral' | 'warn' | 'danger'

export interface RosterStateText {
  label: string
  tone: RosterStateTone
}

export interface RosterRowCounts {
  /** Filas leídas del archivo, según el propio backend. */
  leidas: number
  /** Filas que el confirm incorporará al grupo. */
  incorporables: number
  /** De las incorporables, las que además crean al estudiante en SCIEM. */
  nuevos: number
  yaEnElGrupo: number
  yaInscritos: number
  inconsistentes: number
}

const STATE_TEXT: Record<RosterRowState, RosterStateText> = {
  new_student: { label: 'Nuevo en SCIEM', tone: 'ok' },
  existing_student: { label: 'Se inscribirá', tone: 'ok' },
  already_enrolled: { label: 'Ya en el grupo', tone: 'neutral' },
  inconsistent: { label: 'Inconsistente', tone: 'danger' },
}

const ERROR_ISSUE: Record<RosterRowError, RosterIssue> = {
  missing_sis_code: { category: 'Incompleto', message: 'Falta el código SIS' },
  missing_last_names: { category: 'Incompleto', message: 'Faltan los apellidos' },
  missing_first_names: { category: 'Incompleto', message: 'Faltan los nombres' },
  sis_code_not_numeric: {
    category: 'Formato',
    message: 'El código SIS debe tener solo dígitos',
  },
  sis_code_invalid_length: {
    category: 'Formato',
    message: `El código SIS debe tener entre ${SIS_CODE_MIN_LENGTH} y ${SIS_CODE_MAX_LENGTH} dígitos`,
  },
  last_names_too_long: {
    category: 'Formato',
    message: `Los apellidos superan los ${LAST_NAMES_MAX_LENGTH} caracteres`,
  },
  first_names_too_long: {
    category: 'Formato',
    message: `Los nombres superan los ${FIRST_NAMES_MAX_LENGTH} caracteres`,
  },
  duplicate_row_in_file: { category: 'Duplicado', message: 'Fila repetida en el archivo' },
  conflicting_duplicate_in_file: {
    category: 'Duplicado',
    message: 'Código SIS repetido con datos distintos: no se importa ninguna de las filas',
  },
}

/*
 * Los datos llegan de la red sin validar, así que las búsquedas se hacen sobre
 * una vista indexada por cadena: si el backend añade un estado o un código, la
 * fila se dibuja con un texto de respaldo en lugar de romper la vista.
 */
const STATE_TEXT_BY_KEY: Record<string, RosterStateText | undefined> = STATE_TEXT
const ERROR_ISSUE_BY_KEY: Record<string, RosterIssue | undefined> = ERROR_ISSUE

const UNKNOWN_STATE: RosterStateText = { label: 'Estado no reconocido', tone: 'neutral' }

export function rowStateText(estado: RosterRowState): RosterStateText {
  return STATE_TEXT_BY_KEY[estado] ?? UNKNOWN_STATE
}

export function isIncorporable(estado: RosterRowState): boolean {
  return INCORPORABLE_STATES.includes(estado)
}

/** «3» · «3 y 5» · «3, 5 y 7». */
export function formatRowList(rowNumbers: readonly number[]): string {
  if (rowNumbers.length <= 1) return String(rowNumbers[0] ?? '')

  const head = rowNumbers.slice(0, -1).join(', ')

  return `${head} y ${rowNumbers[rowNumbers.length - 1]}`
}

/**
 * Filas en las que aparece cada código SIS.
 *
 * Se construye con todas las filas de la previsualización, no con las visibles:
 * filtrar o paginar no puede cambiar en qué filas está repetido un código.
 */
export function duplicateRowsBySis(rows: readonly RosterPreviewRow[]): Map<string, number[]> {
  const byCode = new Map<string, number[]>()

  rows.forEach((row) => {
    if (row.codigo_sis === null) return

    const seen = byCode.get(row.codigo_sis) ?? []
    seen.push(row.numero_fila)
    byCode.set(row.codigo_sis, seen)
  })

  return byCode
}

/**
 * Observaciones de una fila, una por inconsistencia.
 *
 * Un código repetido con datos idénticos se importa una vez: el backend marca
 * `duplicate_row_in_file` solo en las filas extra. Si los datos difieren marca
 * `conflicting_duplicate_in_file` en todas: no hay forma de saber cuál es la
 * correcta y no se importa ninguna. El mensaje nombra las filas implicadas.
 */
export function rowIssues(
  row: RosterPreviewRow,
  duplicateRows: readonly number[] = []
): RosterIssue[] {
  return row.errores.map((code) => {
    if (code === 'duplicate_row_in_file' && duplicateRows.length > 0) {
      return { category: 'Duplicado', message: extraCopyMessage(duplicateRows) }
    }

    if (code === 'conflicting_duplicate_in_file' && duplicateRows.length > 0) {
      return { category: 'Duplicado', message: conflictingMessage(duplicateRows) }
    }

    return ERROR_ISSUE_BY_KEY[code] ?? unknownIssue(code)
  })
}

export function countRosterRows(preview: RosterPreviewData): RosterRowCounts {
  const counts = {
    incorporables: 0,
    nuevos: 0,
    yaInscritos: 0,
    inconsistentes: 0,
  }

  preview.filas.forEach((row) => {
    if (isIncorporable(row.estado)) counts.incorporables += 1
    if (row.estado === 'new_student') counts.nuevos += 1
    if (row.estado === 'already_enrolled') counts.yaInscritos += 1
    if (row.estado === 'inconsistent') counts.inconsistentes += 1
  })

  return {
    leidas: preview.total_filas,
    yaEnElGrupo: counts.yaInscritos,
    ...counts,
  }
}

function extraCopyMessage(duplicateRows: readonly number[]): string {
  return `Fila repetida: idéntica a la fila ${duplicateRows[0]}, que sí se importa`
}

function conflictingMessage(duplicateRows: readonly number[]): string {
  return (
    `Código SIS repetido con datos distintos en las filas ${formatRowList(duplicateRows)}. ` +
    'No se importa ninguna porque no hay forma de saber cuál es la correcta'
  )
}

function unknownIssue(code: string): RosterIssue {
  return { category: 'Otro', message: `Inconsistencia informada por el servidor (${code})` }
}
