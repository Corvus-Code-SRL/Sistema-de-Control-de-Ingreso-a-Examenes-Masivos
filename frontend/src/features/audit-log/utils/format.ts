import type { AuditValue } from '../types/auditLog.types'

const TIME_ZONE = 'America/La_Paz'
const LOCALE = 'es-BO'

export const EMPTY_VALUE = '(sin valor)'

/** «15/10/2026 08:30»: fecha y hora de un instante ISO 8601 en la hora de La Paz, donde se usa el sistema. */
export function formatDateTime(iso: string): string {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat(LOCALE, {
      timeZone: TIME_ZONE,
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
      .formatToParts(new Date(iso))
      .map((part) => [part.type, part.value])
  )

  return `${parts.day}/${parts.month}/${parts.year} ${parts.hour}:${parts.minute}`
}

/**
 * Un valor de la operación como texto: vacío, «Sí»/«No», el texto o número tal cual y, para listas y
 * objetos, su JSON en una línea.
 */
export function formatValue(value: AuditValue | undefined): string {
  if (value === undefined || value === null || value === '') return EMPTY_VALUE
  if (typeof value === 'boolean') return value ? 'Sí' : 'No'
  if (typeof value === 'object') return JSON.stringify(value)

  return String(value)
}

/** Iniciales decorativas de un nombre: la del primer nombre y la del penúltimo término. */
export function initialsOf(fullName: string): string {
  const words = fullName.trim().split(/\s+/).filter(Boolean)
  const surname = words.length >= 3 ? words[words.length - 2] : words[1]

  return `${words[0]?.charAt(0) ?? ''}${surname?.charAt(0) ?? ''}`.toUpperCase() || '?'
}
