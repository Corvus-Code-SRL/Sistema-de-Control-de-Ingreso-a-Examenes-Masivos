/** Límites que también impone el servidor (RectifyRiskRecordRequest). */
export const MIN_REASON_LENGTH = 10
export const MAX_REASON_LENGTH = 500

/** Para buscar por nombre hacen falta al menos tres caracteres; el código SIS y el carnet se buscan exactos. */
export const MIN_NAME_SEARCH_LENGTH = 3

/** Mensaje del dato de búsqueda, o null si se puede buscar. */
export function validateSearchTerm(term: string): string | null {
  const trimmed = term.trim()

  if (trimmed === '') return 'Ingrese el código SIS, el carnet de identidad o el nombre del estudiante.'

  if (/\p{L}/u.test(trimmed) && trimmed.length < MIN_NAME_SEARCH_LENGTH) {
    return `Para buscar por nombre ingrese al menos ${MIN_NAME_SEARCH_LENGTH} caracteres.`
  }

  return null
}

/** Mensaje del motivo de la rectificación, o null si es válido. */
export function validateReason(reason: string): string | null {
  const trimmed = reason.trim()

  if (trimmed === '') return 'El motivo es obligatorio'

  if (trimmed.length < MIN_REASON_LENGTH) {
    return `El motivo debe tener al menos ${MIN_REASON_LENGTH} caracteres.`
  }

  if (trimmed.length > MAX_REASON_LENGTH) {
    return `El motivo no puede superar los ${MAX_REASON_LENGTH} caracteres.`
  }

  return null
}
