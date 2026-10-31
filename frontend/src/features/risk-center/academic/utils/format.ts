const TIME_ZONE = 'America/La_Paz'
const LOCALE = 'es-BO'

function parts(value: Date, options: Intl.DateTimeFormatOptions): Record<string, string> {
  return Object.fromEntries(
    new Intl.DateTimeFormat(LOCALE, { timeZone: TIME_ZONE, ...options })
      .formatToParts(value)
      .map((part) => [part.type, part.value])
  )
}

/** «20/10/2026»: la fecha de un instante ISO 8601 en la hora de La Paz, donde se usa el sistema. */
export function formatDate(iso: string): string {
  const { day, month, year } = parts(new Date(iso), { day: '2-digit', month: '2-digit', year: 'numeric' })

  return `${day}/${month}/${year}`
}

/** «10:03» en 24 horas, hora de La Paz. */
export function formatTime(iso: string): string {
  const { hour, minute } = parts(new Date(iso), { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })

  return `${hour}:${minute}`
}

/** «20/10 10:05»: fecha corta y hora, para los hitos del detalle. */
export function formatDayMonthTime(iso: string): string {
  const { day, month } = parts(new Date(iso), { day: '2-digit', month: '2-digit' })

  return `${day}/${month} ${formatTime(iso)}`
}

/** La fecha del examen llega como YYYY-MM-DD, sin zona: se reordena sin pasar por Date. */
export function formatPlainDate(value: string): string {
  const [year, month, day] = value.split('-')

  return year && month && day ? `${day}/${month}/${year}` : value
}

export { formatFileSize } from '@/lib/format'

/**
 * Iniciales decorativas de un nombre completo: la del primer nombre y la del penúltimo término
 * (el apellido paterno cuando hay materno): «José Luis Pérez Núñez» → «JP». El nombre completo
 * llega ya unido, así que es una aproximación; la vista las oculta a los lectores de pantalla.
 */
export function initialsOf(fullName: string): string {
  const words = fullName.trim().split(/\s+/).filter(Boolean)
  const surname = words.length >= 3 ? words[words.length - 2] : words[1]

  return `${words[0]?.charAt(0) ?? ''}${surname?.charAt(0) ?? ''}`.toUpperCase() || '?'
}
