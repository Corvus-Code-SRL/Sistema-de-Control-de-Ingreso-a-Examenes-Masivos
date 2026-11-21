import type { RectificationSummary } from '../types/riskRectification.types'

/**
 * Qué puede hacer el Administrador con un registro activo:
 * - `waiting`: ya hay una solicitud pendiente; solo se espera la respuesta del docente.
 * - `request`: el docente está activo; se le envía una solicitud.
 * - `direct`: el docente lleva inactivo el plazo configurado; se anula sin su confirmación.
 * - `detect`: el docente está inactivo pero no se conoce desde cuándo; el primer intento inicia el plazo.
 * - `blocked`: el docente está inactivo y aún no pasó el plazo; no se puede enviar ni anular todavía.
 */
export type RectifyMode = 'waiting' | 'request' | 'direct' | 'detect' | 'blocked'

export function rectifyModeOf(summary: RectificationSummary): RectifyMode {
  if (summary.solicitud_pendiente !== null) return 'waiting'

  const { docente } = summary

  if (docente.activo) return 'request'
  if (docente.puede_rectificar_sin_confirmacion) return 'direct'

  return docente.puede_rectificar_desde === null ? 'detect' : 'blocked'
}

const HOUR_MS = 60 * 60 * 1000
const DAY_MS = 24 * HOUR_MS

/** «6 días», «5 horas» o «menos de una hora» hasta `iso`; null si ya pasó. */
export function timeLeftUntil(iso: string, now: Date = new Date()): string | null {
  const left = new Date(iso).getTime() - now.getTime()

  if (left <= 0) return null
  if (left >= DAY_MS) {
    const days = Math.floor(left / DAY_MS)

    return days === 1 ? '1 día' : `${days} días`
  }
  if (left >= HOUR_MS) {
    const hours = Math.floor(left / HOUR_MS)

    return hours === 1 ? '1 hora' : `${hours} horas`
  }

  return 'menos de una hora'
}
