import type {
  InvitationDetail,
  InvitationGroup,
  InvitationState,
  InvitationTab,
} from '../types/invitation.types'

const TIME_ZONE = 'America/La_Paz'
const WEEKDAYS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb']

/** Etiqueta visible de cada estado. SIN_EFECTO se muestra como «Vencida» (CA-10). */
export const STATE_LABEL: Record<InvitationState, string> = {
  PENDIENTE: 'Pendiente',
  ACEPTADA: 'Aceptada',
  RECHAZADA: 'Rechazada',
  SIN_EFECTO: 'Vencida',
}

export const TAB_LABEL: Record<InvitationTab, string> = {
  pendientes: 'Pendientes',
  aceptadas: 'Aceptadas',
  cerradas: 'Cerradas',
}

/** Solo una invitación pendiente se puede aceptar o rechazar. */
export function canRespond(state: InvitationState): boolean {
  return state === 'PENDIENTE'
}

function hhmm(time: string | null): string | null {
  return time ? time.slice(0, 5) : null
}

function parts(date: string): { day: string; month: string; year: string; weekday: string } {
  const [year, month, day] = date.slice(0, 10).split('-')
  const weekday = WEEKDAYS[new Date(Date.UTC(Number(year), Number(month) - 1, Number(day))).getUTCDay()]

  return { day, month, year, weekday }
}

/** «mar 20/10 · 10:00»: fecha y hora locales del examen, para la lista. */
export function formatExamShort(date: string, start: string): string {
  const { day, month, weekday } = parts(date)

  return `${weekday} ${day}/${month} · ${hhmm(start)}`
}

/** «mar 20/10/2026 · 10:00 a 11:30». */
export function formatExamLong(date: string, start: string, end: string | null): string {
  const { day, month, year, weekday } = parts(date)
  const range = end ? `${hhmm(start)} a ${hhmm(end)}` : hhmm(start)

  return `${weekday} ${day}/${month}/${year} · ${range}`
}

/**
 * «20/10 08:12» en hora de La Paz, para un instante guardado en UTC. Cada parte se rellena a dos
 * dígitos a mano: según el sistema, Intl con «es-BO» puede devolver «6» en vez de «06».
 */
export function formatMoment(iso: string): string {
  const values = Object.fromEntries(
    new Intl.DateTimeFormat('es-BO', {
      timeZone: TIME_ZONE,
      day: 'numeric',
      month: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hourCycle: 'h23',
    })
      .formatToParts(new Date(iso))
      .map((part) => [part.type, part.value.padStart(2, '0')])
  )

  return `${values.day}/${values.month} ${values.hour}:${values.minute}`
}

export function initials(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean)

  if (words.length === 0) return '?'
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase()

  return (words[0][0] + words[1][0]).toUpperCase()
}

/** «Marcelo Quiroga Andrade» → «Marcelo». */
export function firstName(name: string): string {
  return name.trim().split(/\s+/)[0] ?? name
}

export function groupLabel(group: Pick<InvitationGroup, 'num_grupo'>): string {
  return `Grupo ${group.num_grupo}`
}

/** «Grupo 3», «Grupo 3 y Grupo 6», «Grupo 3, Grupo 6 y Grupo 7». */
export function joinGroups(groups: Pick<InvitationGroup, 'num_grupo'>[]): string {
  const labels = groups.map(groupLabel)

  if (labels.length <= 1) return labels.join('')

  return `${labels.slice(0, -1).join(', ')} y ${labels[labels.length - 1]}`
}

export function plural(count: number, singular: string, pluralForm: string): string {
  return `${count} ${count === 1 ? singular : pluralForm}`
}

export interface CapacityCheck {
  /** Estudiantes que el examen ya tiene. */
  current: number
  /** Estudiantes con los grupos elegidos sumados. */
  after: number
  /** Lugares de todos los ambientes. */
  capacity: number
  /** Los ambientes no alcanzan; solo avisa, no impide aceptar (CA-9). */
  exceeds: boolean
}

export function capacityCheck(invitation: InvitationDetail, selectedIds: number[]): CapacityCheck {
  const current = invitation.grupos_examen.reduce((sum, group) => sum + group.inscritos, 0)
  const added = invitation.mis_grupos
    .filter((group) => selectedIds.includes(group.id_grupo))
    .reduce((sum, group) => sum + group.inscritos, 0)
  const capacity = invitation.ambientes.reduce((sum, room) => sum + room.capacidad, 0)
  const after = current + added

  return { current, after, capacity, exceeds: after > capacity }
}
