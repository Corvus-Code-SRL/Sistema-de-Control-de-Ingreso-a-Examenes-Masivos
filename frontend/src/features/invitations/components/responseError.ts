import { ApiError } from '@/lib/api-client'

/** Mensaje de la API ante un 409: el examen ya abrió el control de ingreso o la invitación ya se respondió. */
export const CONFLICT_FALLBACK = 'La invitación ya no se puede responder: el examen ya abrió el control de ingreso.'

/** Qué decir cuando aceptar o rechazar falla. */
export function responseErrorMessage(cause: unknown, field: string, fallback: string): string {
  if (cause instanceof ApiError) {
    if (cause.status === 409) return cause.message || CONFLICT_FALLBACK
    if (cause.errors[field]?.length) return cause.errors[field][0]
  }

  return fallback
}

export function isConflict(cause: unknown): boolean {
  return cause instanceof ApiError && cause.status === 409
}
