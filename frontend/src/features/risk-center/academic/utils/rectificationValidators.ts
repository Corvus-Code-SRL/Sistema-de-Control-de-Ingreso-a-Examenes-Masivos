/** Límites que también impone el servidor (RectifyRiskRecordRequest). */
export const MIN_REASON_LENGTH = 10
export const MAX_REASON_LENGTH = 500
export const MAX_DESCRIPTION_LENGTH = 2000

/** Para buscar por nombre hacen falta al menos tres caracteres; el código SIS y el carnet se buscan exactos. */
export const MIN_NAME_SEARCH_LENGTH = 3

export interface RectificationValues {
  faultTypeId: number | null
  description: string
  reason: string
}

export interface CurrentRecordValues {
  faultTypeId: number
  description: string | null
}

export interface RectificationErrors {
  faultType?: string
  description?: string
  reason?: string
  /** No cambió ni el tipo de falta ni la descripción. */
  unchanged?: string
}

/** La descripción como la guarda el servidor: recortada y, si queda vacía, sin valor. */
export function normalizeDescription(text: string | null): string | null {
  const trimmed = (text ?? '').trim()

  return trimmed === '' ? null : trimmed
}

/** Mensaje del dato de búsqueda, o null si se puede buscar. */
export function validateSearchTerm(term: string): string | null {
  const trimmed = term.trim()

  if (trimmed === '') return 'Ingrese el código SIS, el carnet de identidad o el nombre del estudiante.'

  if (/\p{L}/u.test(trimmed) && trimmed.length < MIN_NAME_SEARCH_LENGTH) {
    return `Para buscar por nombre ingrese al menos ${MIN_NAME_SEARCH_LENGTH} caracteres.`
  }

  return null
}

export function validateRectification(
  values: RectificationValues,
  current: CurrentRecordValues
): RectificationErrors {
  const errors: RectificationErrors = {}
  const reason = values.reason.trim()
  const description = normalizeDescription(values.description)

  if (values.faultTypeId === null) {
    errors.faultType = 'Seleccione el tipo de falta.'
  }

  if (reason === '') {
    errors.reason = 'El motivo es obligatorio'
  } else if (reason.length < MIN_REASON_LENGTH) {
    errors.reason = `El motivo debe tener al menos ${MIN_REASON_LENGTH} caracteres.`
  } else if (reason.length > MAX_REASON_LENGTH) {
    errors.reason = `El motivo no puede superar los ${MAX_REASON_LENGTH} caracteres.`
  }

  if (description !== null && description.length > MAX_DESCRIPTION_LENGTH) {
    errors.description = `La descripción no puede superar los ${MAX_DESCRIPTION_LENGTH} caracteres.`
  }

  if (
    errors.faultType === undefined &&
    errors.description === undefined &&
    values.faultTypeId === current.faultTypeId &&
    description === normalizeDescription(current.description)
  ) {
    errors.unchanged = 'No hay cambios: modifique el tipo de falta o la descripción para rectificar.'
  }

  return errors
}

export function hasErrors(errors: RectificationErrors): boolean {
  return Object.values(errors).some((message) => message !== undefined)
}
